<?php
declare(strict_types=1);

namespace MOC\Varnish\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;

/**
 * @Flow\Scope("singleton")
 */
class TokenStorage
{

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
     * @throws \Exception
     */
    public function getToken(): string
    {
        $token = $this->cache->get($this->tokenName);
        if ($token === false) {
            $token = Algorithms::generateRandomToken(20);
            $this->storeToken($token);
        }
        return $token;
    }

    /**
     * @param string $token
     * @return void
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Cache\Exception\InvalidDataException
     */
    protected function storeToken(string $token): void
    {
        $this->cache->set($this->tokenName, $token);
    }
}
