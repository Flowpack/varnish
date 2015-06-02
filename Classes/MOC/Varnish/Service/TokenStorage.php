<?php
namespace MOC\Varnish\Service;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Algorithms;

/**
 * @Flow\Scope("singleton")
 */
class TokenStorage {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Cache\Frontend\StringFrontend
	 */
	protected $cache;

	/**
	 * @var string
	 */
	protected $tokenName = 'VarnishSiteToken';

	/**
	 * Fetch the token or generate a new random token
	 *
	 * @return string
	 */
	public function getToken() {
		$token = $this->cache->get($this->tokenName);

		if ($token === FALSE) {
			$newToken = Algorithms::generateRandomToken(20);
			$this->storeToken($newToken);
			return $newToken;
		} else {
			return $token;
		}
	}

	/**
	 * @param string $token
	 */
	protected function storeToken($token) {
		$this->cache->set($this->tokenName, $token);
	}
}