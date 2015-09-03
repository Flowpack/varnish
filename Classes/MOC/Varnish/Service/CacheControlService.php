<?php
namespace MOC\Varnish\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ControllerInterface;
use TYPO3\Flow\Mvc\RequestInterface;
use TYPO3\Flow\Mvc\ResponseInterface;
use TYPO3\Flow\Http\Response;
use TYPO3\Neos\Controller\Frontend\NodeController;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Service for adding cache headers to a to-be-sent response
 *
 * @Flow\Scope("singleton")
 */
class CacheControlService {

	/**
	 * @var \MOC\Varnish\Aspects\ContentCacheAspect
	 * @Flow\Inject
	 */
	protected $contentCacheAspect;

	/**
	 * @var \MOC\Varnish\Cache\MetadataAwareStringFrontend
	 * @Flow\Inject
	 */
	protected $contentCacheFrontend;

	/**
	 * @var \MOC\Varnish\Service\TokenStorage
	 * @Flow\Inject
	 */
	protected $tokenStorage;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
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
	 */
	public function addHeaders(RequestInterface $request, ResponseInterface $response, ControllerInterface $controller) {
		if (!$response instanceof Response || !$controller instanceof NodeController) {
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
		if ($node->hasProperty('disableVarnishCache') && $node->getProperty('disableVarnishCache') === TRUE) {
			return;
		}

		if ($this->contentCacheAspect->isEvaluatedUncached()) {
			$response->getHeaders()->setCacheControlDirective('no-cache');
		} else {
			list($tags, $cacheLifetime) = $this->getCacheTagsAndLifetime();
			if (count($tags) > 0) {
				$response->setHeader('X-Cache-Tags', implode(',', $tags));
			}

			$response->setHeader('X-Site', $this->tokenStorage->getToken());

			$nodeLifetime = $node->getProperty('cacheTimeToLive');
			if ($nodeLifetime === '' || $nodeLifetime === NULL) {
				$defaultLifetime = $this->settings['cacheHeaders']['defaultSharedMaximumAge'];
				$timeToLive = $defaultLifetime;
				if ($defaultLifetime === NULL) {
					$timeToLive = $cacheLifetime;
				} elseif ($cacheLifetime !== NULL) {
					$timeToLive = min($defaultLifetime, $cacheLifetime);
				}
			} else {
				$timeToLive = $nodeLifetime;
			}

			if ($timeToLive !== NULL) {
				$response->setSharedMaximumAge(intval($timeToLive));
			}
		}
	}

	/**
	 * Get cache tags and lifetime from the cache metadata that was extracted by the special cache frontend
	 *
	 * @return array
	 */
	protected function getCacheTagsAndLifetime() {
		$lifetime = NULL;
		$tags = array();
		$entriesMetadata = $this->contentCacheFrontend->getAllMetadata();
		foreach ($entriesMetadata as $identifier => $metadata) {
			$entryTags = isset($metadata['tags']) ? $metadata['tags'] : array();
			$entryLifetime = isset($metadata['lifetime']) ? $metadata['lifetime'] : NULL;

			if ($entryLifetime !== NULL) {
				if ($lifetime === NULL) {
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