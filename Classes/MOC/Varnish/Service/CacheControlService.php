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
 * @package MOC\Varnish
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
		if ($response instanceof Response && $controller instanceof NodeController) {
			$arguments = $controller->getControllerContext()->getArguments();
			if ($arguments->hasArgument('node')) {
				$node = $arguments->getArgument('node')->getValue();

				if ($node instanceof NodeInterface && $node->getContext()->getWorkspaceName() === 'live') {
					if($node->getProperty('disableVarnishCache') === NULL || $node->getProperty('disableVarnishCache') === 0) {
						$response->setHeader('X-Neos-NodeIdentifier', $node->getIdentifier());
						$timeToLive = $node->getProperty('cacheTimeToLive');
						if ($timeToLive === '' || $timeToLive === NULL) {
							$timeToLive = $this->settings['cacheHeaders']['defaultSharedMaximumAge'];
						}
						$response->setSharedMaximumAge(intval($timeToLive));
					};

				}
			}
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
		$this->varnishBanService->banByNode($documentNode);
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
}