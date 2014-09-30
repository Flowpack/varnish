<?php
namespace MOC\Varnish\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Log\SystemLoggerInterface;

/**
 * Class VarnishBanService
 *
 * @package MOC\Varnish
 * @Flow\Scope("singleton")
 */
class VarnishBanService {

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
	 * Ban a document in varnish by sending a BAN request to varnish
	 *
	 * @param NodeInterface $node The node to ban in Varnish
	 * @return void
	 */
	public function banByNode(NodeInterface $node) {
		$this->banByNodeIdentifier($node->getIdentifier());
	}

	/**
	 * Ban a document in varnish by sending a BAN request to varnish
	 *
	 * @param integer $nodeIdentifier The identifier of the node to ban in Varnish
	 * @return void
	 */
	public function banByNodeIdentifier($nodeIdentifier) {
		$varnishUrl = $this->settings['varnishUrl'] ? $this->settings['varnishUrl'] : 'http://127.0.0.1/';
		$url = new Uri($varnishUrl);
		$request = Request::create($url, 'BAN');
		$request->setHeader('X-Varnish-Ban-Neos-NodeIdentifier', $nodeIdentifier);
		$engine = new CurlEngine();
		$varnishResponse = $engine->sendRequest($request);
		if ($varnishResponse->getStatusCode() === 200) {
			$this->systemLogger->log('Cleared varnish cache for node identifier ' . $nodeIdentifier);
		} else {
			$this->systemLogger->log('Error calling varnish with BAN request. Error: ' . $varnishResponse->getStatusCode() . ' ' . $varnishResponse->getStatusMessageByCode($varnishResponse->getStatusCode()), LOG_ERR);
		}
	}

	/**
	 * Clear all cache for a given domain.
	 *
	 * The domain is required since the expected VCL only bans for a given domain.
	 *
	 * @param string $domain
	 * @return void
	 */
	public function banAll($domain = NULL) {
		$varnishUrl = $this->settings['varnishUrl'] ? $this->settings['varnishUrl'] : 'http://127.0.0.1/';
		$url = new Uri($varnishUrl);
		$request = Request::create($url, 'BAN');
		$request->setHeader('Varnish-Ban-All', '1');
		if ($domain !== NULL) {
			$request->setHeader('Host', $domain);
		}
		$engine = new CurlEngine();
		$varnishResponse = $engine->sendRequest($request);
		if ($varnishResponse->getStatusCode() === 200) {
			$this->systemLogger->log('Cleared all cache on domain ' . $domain);
		} else {
			$this->systemLogger->log('Error calling varnish with BAN all request. Error: ' . $varnishResponse->getStatusCode() . ' ' . $varnishResponse->getStatusMessageByCode($varnishResponse->getStatusCode()), LOG_ERR);
		}
	}

}