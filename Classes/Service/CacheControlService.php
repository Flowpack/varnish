<?php
namespace MOC\Varnish\Service;

use MOC\Varnish\Aspects\ContentCacheAspect;
use MOC\Varnish\Cache\MetadataAwareStringFrontend;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\RequestInterface;
use Neos\Neos\Controller\Frontend\NodeController;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for adding cache headers to a to-be-sent response
 *
 * @Flow\Scope("singleton")
 */
class CacheControlService
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
     * @var array
     */
    protected $settings;

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Adds cache headers to the response.
     *
     * Called via a signal triggered by the MVC Dispatcher
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ControllerInterface $controller
     * @return void
     * @throws NodeException
     * @throws NoSuchArgumentException
     */
    public function addHeaders(RequestInterface $request, ResponseInterface $response, ControllerInterface $controller)
    {
        if (isset($this->settings['cacheHeaders']['disabled']) && $this->settings['cacheHeaders']['disabled'] === true) {
            $this->logger->debug(sprintf('Varnish cache headers disabled (see configuration setting MOC.Varnish.cacheHeaders.disabled)'), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }
        if (!$response instanceof ActionResponse || !$controller instanceof NodeController) {
            return;
        }
        $arguments = $controller->getControllerContext()->getArguments();
        if (!$arguments->hasArgument('node')) {
            return;
        }
        $node = $arguments->getArgument('node')->getValue();
        if (!$node instanceof NodeInterface) {
            return;
        }
        if ($node->getContext()->getWorkspaceName() !== 'live') {
            return;
        }
        if ($node->hasProperty('disableVarnishCache') && $node->getProperty('disableVarnishCache') === true) {
            $this->logger->debug(sprintf('Varnish cache headers skipped due to property "disableVarnishCache" for node "%s" (%s)', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        if ($this->contentCacheAspect->isEvaluatedUncached()) {
            $this->logger->debug(sprintf('Varnish cache disabled due to uncachable content for node "%s" (%s)', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            $response->getHeaders()->setCacheControlDirective('no-cache');
        } else {
            list($tags, $cacheLifetime) = $this->getCacheTagsAndLifetime();
            if (count($tags) > 0) {
                $response->setHeader('X-Cache-Tags', implode(',', $tags));
            }

            $response->setHeader('X-Site', $this->tokenStorage->getToken());

            $nodeLifetime = $node->getProperty('cacheTimeToLive');
            if ($nodeLifetime === '' || $nodeLifetime === null) {
                $defaultLifetime = isset($this->settings['cacheHeaders']['defaultSharedMaximumAge']) ? $this->settings['cacheHeaders']['defaultSharedMaximumAge'] : null;
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
                $response->setSharedMaximumAge(intval($timeToLive));
                $this->logger->debug(sprintf('Varnish cache enabled for node "%s" (%s) with max-age "%u"', $node->getLabel(), $node->getPath(), $timeToLive), LogEnvironment::fromMethodName(__METHOD__));
            } else {
                $this->logger->debug(sprintf('Varnish cache headers not sent for node "%s" (%s) due to no max-age', $node->getLabel(), $node->getPath()), LogEnvironment::fromMethodName(__METHOD__));
            }
        }
    }

    /**
     * Get cache tags and lifetime from the cache metadata that was extracted by the special cache frontend
     *
     * @return array
     */
    protected function getCacheTagsAndLifetime()
    {
        $lifetime = null;
        $tags = array();
        $entriesMetadata = $this->contentCacheFrontend->getAllMetadata();
        foreach ($entriesMetadata as $identifier => $metadata) {
            $entryTags = isset($metadata['tags']) ? $metadata['tags'] : array();
            $entryLifetime = isset($metadata['lifetime']) ? $metadata['lifetime'] : null;

            if ($entryLifetime !== null) {
                if ($lifetime === null) {
                    $lifetime = $entryLifetime;
                } else {
                    $lifetime = min($lifetime, $entryLifetime);
                }
            }
            $tags = array_unique(array_merge($tags, $entryTags));
        }
        return array($tags, $lifetime);
    }
}
