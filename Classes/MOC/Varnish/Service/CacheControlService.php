<?php
namespace MOC\Varnish\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ControllerInterface;
use TYPO3\Flow\Mvc\RequestInterface;
use TYPO3\Flow\Mvc\ResponseInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Flow\Http\Response;

use TYPO3\Neos\Controller\Frontend\NodeController;
use TYPO3\Flow\Log\SystemLoggerInterface;

/**
 * Service for adding cache headers to a to-be-sent response
 *
 * Heavily inspired by https://github.com/techdivision/TechDivision.NeosVarnishAdaptor/blob/master/Classes/TechDivision/NeosVarnishAdaptor/Service/CacheControlService.php
 *
 * @Flow\Scope("singleton")
 */
class CacheControlService {

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * @Flow\Inject
	 * @var SystemLoggerInterface
	 */
	protected $systemLogger;

	/**
	 * @var VarnishBanService
	 * @Flow\Inject
	 */
	protected $varnishBanService;

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
			$response->setHeader('X-Neos-NodeIdentifier', $node->getIdentifier());

			list($tags, $cacheLifetime) = $this->getCacheTagsAndLifetime();
			$response->setHeader('X-Neos-Tags', implode(',', $tags));

			$nodeLifetime = $node->getProperty('cacheTimeToLive');
			if ($nodeLifetime === '' || $nodeLifetime === NULL) {
				$defaultLifetime = $this->settings['cacheHeaders']['defaultSharedMaximumAge'];
				if ($defaultLifetime === NULL) {
					$timeToLive = $cacheLifetime;
				} elseif ($cacheLifetime !== NULL) {
					$timeToLive = min($defaultLifetime, $cacheLifetime);
				} else {
					$timeToLive = $cacheLifetime;
				}
			} else {
				$timeToLive = $nodeLifetime;
			}
			$response->setSharedMaximumAge(intval($timeToLive));
		}
	}

	/**
	 *
	 * @param NodeInterface $node The node which has changed in some way
	 * @return void
	 */
	public function handleNodePublished(NodeInterface $node) {
		$documentNode = $this->getClosestDocumentNode($node);
		/** @todo:
		 * * Figure out which cache-tags the document is cached with.... Not sure if this makes sense
		 * * Find cache lifetime from the cache of this particular node
		 * * Implement async banning*
		 */
		if ($documentNode && $this->settings['enableCacheBanningWhenNodePublished'] === TRUE) {
			$this->varnishBanService->banByNode($documentNode);
		}

	}

	/**
	 * Duplicated from TypoScriptView
	 * @param NodeInterface $node
	 * @return NodeInterface
	 */
	protected function getClosestDocumentNode(NodeInterface $node) {
		while ($node !== NULL && !$node->getNodeType()->isOfType('TYPO3.Neos:Document')) {
			$node = $node->getParent();
		}
		return $node;
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