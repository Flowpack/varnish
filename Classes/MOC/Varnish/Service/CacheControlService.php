<?php
namespace MOC\Varnish\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ControllerInterface;
use TYPO3\Flow\Mvc\RequestInterface;
use TYPO3\Flow\Mvc\ResponseInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Flow\Http\Response;
use TYPO3\Neos\Controller\Frontend\NodeController;

/**
 * Service for adding cache headers to a to-be-sent response
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
		$response->setHeader('X-Neos-NodeIdentifier', $node->getIdentifier());
		$timeToLive = $node->getProperty('cacheTimeToLive');
		if ($timeToLive === '' || $timeToLive === NULL) {
			$timeToLive = $this->settings['cacheHeaders']['defaultSharedMaximumAge'];
		}
		$response->setSharedMaximumAge(intval($timeToLive));
	}

}