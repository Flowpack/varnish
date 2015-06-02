<?php
namespace MOC\Varnish\Service;

use TYPO3\Flow\Annotations as Flow;

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
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Utility\Algorithms
	 */
	protected $algorithms;

	/**
	 * @var string
	 */
	protected $tokenName = 'VarnishSiteToken';

	/**
	 * @return string
	 */
	public function getToken() {
		$token = $this->cache->get($this->tokenName);

		if ($token === FALSE) {
			$newToken = $this->algorithms->generateRandomToken(20);
			$this->storeToken($newToken);
			return $newToken;
		} else {
			return $token;
		}
	}

	/**
	 * @param $token
	 */
	public function storeToken($token) {
		$this->cache->set($this->tokenName, $token);
	}
}