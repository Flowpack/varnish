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
		$this->outputLine('Clear all cache on domain ' . $domain);
		$this->varnishBanService->banAll($domain);
	}
}