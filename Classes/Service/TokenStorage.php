<?php
namespace MOC\Varnish\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;

/**
 * @Flow\Scope("singleton")
 */
class TokenStorage {

	/**
	 * @Flow\Inject
	 * @var \Neos\Cache\Frontend\StringFrontend
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
			$token = Algorithms::generateRandomToken(20);
			$this->storeToken($token);
		}
		return $token;
	}

	/**
	 * @param string $token
	 * @return void
	 */
	protected function storeToken($token) {
		$this->cache->set($this->tokenName, $token);
	}
}