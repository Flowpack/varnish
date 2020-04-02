<?php
declare(strict_types=1);

namespace Moc\Varnish\Http;

use MOC\Varnish\Aspects\ContentCacheAspect;
use MOC\Varnish\Cache\MetadataAwareStringFrontend;
use MOC\Varnish\Service\TokenStorage;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\DispatchComponent;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

class CacheControlHeaderComponent implements ComponentInterface
{
    /**
     * @var ContentCacheAspect
     * @Flow\Inject
     */
    protected $contentCacheAspect;

    /**
     * @var MetadataAwareStringFrontend
     * @Flow\Inject
     */
    protected $contentCacheFrontend;

    /**
     * @var TokenStorage
     * @Flow\Inject
     */
    protected $tokenStorage;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\InjectConfiguration()
     * @var array
     */
    protected $cacheHeaderSettings;

    /**
     * @Flow\Inject
     * @var PropertyMapper
     */
    protected $propertyMapper;

    /**
     * @param ComponentContext $componentContext
     * @return void
     * @throws NodeException
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

        if ($node->hasProperty('disableVarnishCache') && $node->getProperty('disableVarnishCache') === true) {
            $this->logger->debug(sprintf('Varnish cache headers skipped due to property "disableVarnishCache" for node "%s" (%s)', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        $response = $componentContext->getHttpResponse();

        if ($response->hasHeader('Set-Cookie')) {
            return;
        }

        $modifiedResponse = $response;

        if ($this->contentCacheAspect->isEvaluatedUncached()) {
            $this->logger->debug(sprintf('Varnish cache disabled due to uncachable content for node "%s" (%s)', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            // $modifiedResponse = $modifiedResponse->getHeaders()->setCacheControlDirective('no-cache');
            return;
        }
        list($tags, $cacheLifetime) = $this->getCacheTagsAndLifetime();

        if (count($tags) > 0) {
            $modifiedResponse = $modifiedResponse->withHeader('X-Cache-Tags', $tags);
        }

        $modifiedResponse = $modifiedResponse->withHeader('X-Site', $this->tokenStorage->getToken());

        $nodeLifetime = $node->getProperty('cacheTimeToLive');

        if ($nodeLifetime === '' || $nodeLifetime === null) {
            $defaultLifetime = $this->settings['cacheHeaders']['defaultSharedMaximumAge'] ?? null;
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
            // $response->setSharedMaximumAge((int)$timeToLive);
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
