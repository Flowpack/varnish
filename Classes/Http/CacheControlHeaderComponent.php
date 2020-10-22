<?php
declare(strict_types=1);

namespace Moc\Varnish\Http;

use MOC\Varnish\Aspects\ContentCacheAspect;
use MOC\Varnish\Cache\MetadataAwareStringFrontend;
use MOC\Varnish\Service\TokenStorage;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\DispatchComponent;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Property\PropertyMapper;
use Psr\Log\LoggerInterface;

class CacheControlHeaderComponent implements ComponentInterface
{

    /**
     * @var array
     * @Flow\InjectConfiguration(path="cacheHeaders")
     */
    protected $cacheHeaderSettings;

    /**
     * @var ContentCacheAspect
     * @Flow\Inject
     */
    protected $contentCacheAspect;

    /**
     * @var TokenStorage
     * @Flow\Inject
     */
    protected $tokenStorage;

    /**
     * @var LoggerInterface
     * @Flow\Inject
     */
    protected $logger;

    /**
     * @var PropertyMapper
     * @Flow\Inject
     */
    protected $propertyMapper;

    /**
     * @var MetadataAwareStringFrontend
     */
    protected $contentCacheFrontend;

    /**
     * @param ComponentContext $componentContext
     * @return void
     * @throws NoSuchArgumentException
     * @throws \Exception
     * @api
     */
    public function handle(ComponentContext $componentContext)
    {
        if ($this->cacheHeaderSettings['disabled'] ?? false) {
            $this->logger->debug(sprintf('Varnish cache headers disabled (see configuration setting MOC.Varnish.cacheHeaders.disabled)'), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        /** @var \Neos\Flow\Mvc\ActionRequest $actionRequest */
        $actionRequest = $componentContext->getParameter(DispatchComponent::class, 'actionRequest');
        if (!$actionRequest->hasArgument('node') || !$actionRequest->getArgument('node')) {
            return;
        }

        try {
            $node = $this->propertyMapper->convert($actionRequest->getArgument('node'), NodeInterface::class);
        } catch (\Exception $e) {
            return;
        }

        if ($node->getContext()->getWorkspaceName() !== 'live') {
            return;
        }

        $response = $componentContext->getHttpResponse();

        if ($node->hasProperty('disableVarnishCache') && $node->getProperty('disableVarnishCache') === true) {
            $this->logger->debug(sprintf('Varnish cache headers skipped due to property "disableVarnishCache" for node "%s" (%s)', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));

            $modifiedResponse = $response->withAddedHeader('Cache-Control', 'no-cache');
            $componentContext->replaceHttpResponse($modifiedResponse);
            return;
        }

        if ($response->hasHeader('Set-Cookie')) {
            return;
        }

        $modifiedResponse = $response;

        if ($this->contentCacheAspect->isEvaluatedUncached()) {
            $this->logger->debug(sprintf('Varnish cache disabled due to uncachable content for node "%s" (%s)', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            $modifiedResponse = $modifiedResponse->withAddedHeader('Cache-Control', 'no-cache');
            $componentContext->replaceHttpResponse($modifiedResponse);
            return;
        }
        list($tags, $cacheLifetime) = $this->getCacheTagsAndLifetime();

        if (count($tags) > 0) {
            $modifiedResponse = $modifiedResponse->withHeader('X-Cache-Tags', $tags);
        }

        $modifiedResponse = $modifiedResponse->withHeader('X-Site', $this->tokenStorage->getToken());

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
            $modifiedResponse = $modifiedResponse->withAddedHeader('Cache-Control', sprintf('public, s-maxage=%d', $timeToLive));
            $this->logger->debug(sprintf('Varnish cache enabled for node "%s" (%s) with max-age "%u"', $node->getLabel(), $node->getPath(), $timeToLive), LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->logger->debug(sprintf('Varnish cache headers not sent for node "%s" (%s) due to no max-age', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
        }

        $componentContext->replaceHttpResponse($modifiedResponse);
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
