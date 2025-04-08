<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Middleware;

use Flowpack\Varnish\Aspects\ContentCacheAspect;
use Flowpack\Varnish\Cache\MetadataAwareStringFrontend;
use Flowpack\Varnish\Service\CacheTagService;
use Flowpack\Varnish\Service\TokenStorage;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Property\PropertyMapper;
use Neos\Neos\Domain\NodeLabel\NodeLabelGeneratorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class CacheControlHeaderComponent implements MiddlewareInterface
{
    protected const HEADER_CACHE_CONTROL = 'Cache-Control';

    /**
     * @var array
     * @Flow\InjectConfiguration(path="cacheHeaders")
     */
    protected array $cacheHeaderSettings;

    #[Flow\Inject]
    protected ContentCacheAspect $contentCacheAspect;

    #[Flow\Inject]
    protected TokenStorage $tokenStorage;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\Inject]
    protected PropertyMapper $propertyMapper;

    #[Flow\Inject]
    protected CacheTagService $cacheTagService;

    /**
     * @var MetadataAwareStringFrontend
     */
    protected $contentCacheFrontend;

    #[Flow\Inject(lazy: false)]
    protected ActionRequestFactory $actionRequestFactory;

    #[Flow\Inject]
    protected NodeLabelGeneratorInterface $nodeLabelGenerator;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($response->hasHeader('Set-Cookie')) {
            return $response;
        }

        if ($this->cacheHeaderSettings['disabled'] ?? false) {
            $this->logger->info(sprintf('Varnish cache headers disabled (see configuration setting Flowpack.Varnish.cacheHeaders.disabled)'), LogEnvironment::fromMethodName(__METHOD__));
            return $response;
        }

        $routingMatchResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS) ?? [];
        $actionRequest = $this->actionRequestFactory->createActionRequest($request, $routingMatchResults);

        if (!$actionRequest->hasArgument('node') || $actionRequest->getArgument('node') === '') {
            $this->logger->debug(sprintf('A node for the request "%s" could not be found', $request->getUri()), LogEnvironment::fromMethodName(__METHOD__));
            return $response;
        }

        try {
            $node = $this->propertyMapper->convert($actionRequest->getArgument('node'), Node::class);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf('The node argument was set to "%s", but it could not be converted into a node: %s', $actionRequest->getArgument('node'), $exception->getMessage()));
            return $response;
        }

        if (!$node instanceof Node) {
            return $response;
        }

        if (!$node->workspaceName->isLive()) {
            return $response;
        }

        if ($node->hasProperty('disableVarnishCache') && $node->getProperty('disableVarnishCache') === true) {
            $this->logger->debug(sprintf('Varnish cache headers skipped due to property "disableVarnishCache" for node "%s" (%s)', $this->nodeLabelGenerator->getLabel($node), $node->aggregateId->value), LogEnvironment::fromMethodName(__METHOD__));

            return $response->withAddedHeader(self::HEADER_CACHE_CONTROL, 'no-cache');
        }

        if ($this->contentCacheAspect->isEvaluatedUncached()) {
            $this->logger->debug(sprintf('Varnish cache disabled due to uncachable content for node "%s" (%s)', $this->nodeLabelGenerator->getLabel($node), $node->aggregateId->value), LogEnvironment::fromMethodName(__METHOD__));
            return $response->withAddedHeader(self::HEADER_CACHE_CONTROL, 'no-cache');
        }

        [$tags, $cacheLifetime] = $this->getCacheTagsAndLifetime();

        if (count($tags) > 0) {
            $shortenedTags = $this->cacheTagService->shortenTags($tags);
            $response = $response->withHeader('X-Cache-Tags', implode(',', $shortenedTags));
        }

        $response = $response->withHeader('X-Site', $this->tokenStorage->getToken());

        $nodeLifetime = $node->getProperty('cacheTimeToLive');

        if ($nodeLifetime === '' || $nodeLifetime === null) {
            $defaultLifetime = $this->cacheHeaderSettings['defaultSharedMaximumAge'] ?? null;
            $timeToLive = $defaultLifetime;
            if ($defaultLifetime === null) {
                $timeToLive = $cacheLifetime;
            } elseif ($cacheLifetime !== null) {
                $timeToLive = min($defaultLifetime, $cacheLifetime);
            }
        } else {
            $timeToLive = $nodeLifetime;
        }

        if ($timeToLive !== null) {
            $response = $response->withAddedHeader(self::HEADER_CACHE_CONTROL, sprintf('public, s-maxage=%d', $timeToLive));
            $this->logger->debug(sprintf('Varnish cache enabled for node "%s" (%s) with max-age "%u"', $this->nodeLabelGenerator->getLabel($node), $node->aggregateId->value, $timeToLive), LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->logger->debug(sprintf('Varnish cache headers not sent for node "%s" (%s) due to no max-age', $this->nodeLabelGenerator->getLabel($node), $node->aggregateId->value), LogEnvironment::fromMethodName(__METHOD__));
        }

        if ($this->cacheHeaderSettings['debug'] ?? false) {
            $response = $response->withAddedHeader('X-Cache-Debug', '1');
        }

        return $response;
    }

    /**
     * Get cache tags and lifetime from the cache metadata that was extracted by the special cache frontend
     *
     * @return array
     */
    protected function getCacheTagsAndLifetime(): array
    {
        $lifetime = null;
        $tags = [];
        $entriesMetadata = $this->contentCacheFrontend->getAllMetadata();

        foreach ($entriesMetadata as $identifier => $metadata) {
            $entryTags = $metadata['tags'] ?? [];
            $entryLifetime = $metadata['lifetime'] ?? null;

            if ($entryLifetime !== null) {
                if ($lifetime === null) {
                    $lifetime = $entryLifetime;
                } else {
                    $lifetime = min($lifetime, $entryLifetime);
                }
            }
            $tags = array_unique(array_merge($tags, $entryTags));
        }
        return [$tags, $lifetime];
    }
}
