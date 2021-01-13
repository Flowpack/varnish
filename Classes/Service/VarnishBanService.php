<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Service;

use FOS\HttpCache\CacheInvalidator;
use FOS\HttpCache\Exception\ExceptionCollection;
use FOS\HttpCache\Exception\ProxyResponseException;
use FOS\HttpCache\Exception\ProxyUnreachableException;
use FOS\HttpCache\ProxyClient;
use Flowpack\Varnish\Service\ProxyClient\Varnish;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;

/**
 * @Flow\Scope("singleton")
 */
class VarnishBanService
{

    /**
     * @Flow\Inject
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var \Flowpack\Varnish\Service\TokenStorage
     */
    protected $tokenStorage;

    /**
     * @Flow\Inject
     * @var CacheTagService
     */
    protected $cacheTagService;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var Varnish
     */
    protected $varnishProxyClient;

    /**
     * @var CacheInvalidator
     */
    protected $cacheInvalidator;

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function initializeObject(): void
    {
        $httpDispatcher = new ProxyClient\HttpDispatcher($this->prepareVarnishUrls());
        $options = [
            'header_length' => $this->settings['maximumHeaderLength'] ?? 7500,
            'default_ban_headers' => [
                'X-Site' => $this->tokenStorage->getToken()
            ]
        ];
        $this->varnishProxyClient = new Varnish($httpDispatcher, $options);
        $this->cacheInvalidator = new CacheInvalidator($this->varnishProxyClient);
    }

    /**
     * Clear all cache in Varnish for a optionally given domain & content type.
     *
     * The hosts parameter can either be a regular expression, e.g.
     * '^(www\.)?(this|that)\.com$' or an array of exact host names, e.g.
     * ['example.com', 'other.net']. If the parameter is empty, all hosts
     * are matched.
     *
     * @param array|string $domains The domains to flush, e.g. "example.com"
     * @param string $contentType The mime type to flush, e.g. "image/png"
     * @return void
     */
    public function banAll($domains = null, $contentType = null): void
    {
        $this->varnishProxyClient->forHosts(...$this->domainsToArray($domains));
        $this->cacheInvalidator->invalidateRegex('.*', $contentType, $domains);
        $this->logger->debug(sprintf('Clearing all Varnish cache%s%s', $domains ? ' for domains "' . (is_array($domains) ? implode(', ', $domains) : $domains) . '"' : '', $contentType ? ' with content type "' . $contentType . '"' : ''), LogEnvironment::fromMethodName(__METHOD__));
        $this->execute();
    }

    /**
     * Clear all cache in Varnish for given tags.
     *
     * The hosts parameter can either be a regular expression, e.g.
     * '^(www\.)?(this|that)\.com$' or an array of exact host names, e.g.
     * ['example.com', 'other.net']. If the parameter is empty, all hosts
     * are matched.
     *
     * @param array $tags
     * @param array|string $domains The domain to flush, e.g. "example.com"
     * @return void
     */
    public function banByTags(array $tags, $domains = null): void
    {
        if (count($this->settings['ignoredCacheTags']) > 0) {
            $tags = array_diff($tags, $this->settings['ignoredCacheTags']);
        }

        $tags = $this->cacheTagService->sanitizeTags($tags);
        $tags = $this->cacheTagService->shortenTags($tags);

        $this->varnishProxyClient->forHosts(...$this->domainsToArray($domains));
        $this->cacheInvalidator->invalidateTags($tags);
        $this->logger->debug(sprintf('Cleared Varnish cache for tags "%s"%s', implode(',', $tags), $domains ? ' for domains "' . (is_array($domains) ? implode(', ', $domains) : $domains) . '"' : ''), LogEnvironment::fromMethodName(__METHOD__));
        $this->execute();
    }

    protected function execute(): void
    {
        try {
            $this->cacheInvalidator->flush();
        } catch (ExceptionCollection $exceptions) {
            foreach ($exceptions as $exception) {
                if ($exception instanceof ProxyResponseException) {
                    $this->logger->error(sprintf('Error calling Varnish with BAN request (caching proxy returned an error response). Error %s', $exception->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
                } elseif ($exception instanceof ProxyUnreachableException) {
                    $this->logger->error(sprintf('Error calling Varnish with BAN request (cannot connect to the caching proxy). Error %s', $exception->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
                } else {
                    $this->logger->error(sprintf('Error calling Varnish with BAN request. Error %s', $exception->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
                }
            }
        }
    }

    /**
     * @param string|string[]|null $domains
     * @return array
     */
    private function domainsToArray($domains = null): array
    {
        $domains = $domains ?? [];
        return is_array($domains) ? $domains : [$domains];
    }

    private function prepareVarnishUrls(): array
    {
        $varnishUrls = $this->settings['varnishUrl'] ?? ['http://127.0.0.1'];

        if (is_string($varnishUrls)) {
            if (strpos($varnishUrls, ',') > 0) {
                $varnishUrls = explode(',', $varnishUrls);
            } else {
                $varnishUrls = [$varnishUrls];
            }
        }

        // Remove trailing slash as it will break the Varnish ProxyClient
        array_walk($varnishUrls, static function (&$varnishUrl) {
            $varnishUrl = rtrim($varnishUrl, '/');
        });

        return $varnishUrls;
    }
}
