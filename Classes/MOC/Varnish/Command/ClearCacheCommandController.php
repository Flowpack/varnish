<?php
namespace MOC\Varnish\Command;

use MOC\Varnish\Service\VarnishBanService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Http\Request;

/**
 * @Flow\Scope("singleton")
 */
class ClearCacheCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * @var VarnishBanService
	 * @Flow\Inject
	 */
	protected $varnishBanService;

	/**
	 * Send BAN request to Varnish to clear all cache for a given domain.
	 *
	 * @param string domain
	 */
	public function clearAllCacheCommand($domain) {
		$url = new Uri('https://mocdk.slack.com/services/hooks/incoming-webhook?token=ueNkO9y9Jg6Z6jwqbcEGOAM8');
		$request = Request::create($url, 'POST');
		$data = array(
			'username' => 'webhookbot',
			'text' => 'TEST',
			'icon_emoji' => ':hatched_chick:'
		);
		$request->setContent(json_encode($data));
		$engine = new CurlEngine();
		$response = $engine->sendRequest($request);
		print $response->getContent() . PHP_EOL;

		return;
		$this->outputLine('Clear all cache on domain ' . $domain);
		$this->varnishBanService->banAll($domain);
	}
}