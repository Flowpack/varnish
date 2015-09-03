<?php
namespace MOC\Varnish\Service;

use FOS\HttpCache\CacheInvalidator;
use FOS\HttpCache\Exception\ExceptionCollection;
use FOS\HttpCache\Exception\ProxyResponseException;
use FOS\HttpCache\Exception\ProxyUnreachableException;
use FOS\HttpCache\Handler\TagHandler;
use FOS\HttpCache\ProxyClient;
use TYPO3\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class VarnishBanService {

	/**
	 * @Flow\Inject
	 * @var \MOC\Varnish\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @Flow\Inject
	 * @var \MOC\Varnish\Service\TokenStorage
	 */
	protected $tokenStorage;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var ProxyClient\Varnish
	 */
	protected $varnishProxyClient;

	/**
	 * @var CacheInvalidator
	 */
	protected $cacheInvalidator;

	/**
	 * @var TagHandler
	 */
	protected $tagHandler;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * @return void
	 */
	public function initializeObject() {
		$varnishUrls = is_array($this->settings['varnishUrl']) ? $this->settings['varnishUrl'] : array($this->settings['varnishUrl'] ?: 'http://127.0.0.1');
		// Remove trailing slash as it will break the Varnish ProxyClient
		array_walk($varnishUrls, function(&$varnishUrl) {
			$varnishUrl = rtrim($varnishUrl, '/');
		});
		$this->varnishProxyClient = new ProxyClient\Varnish($varnishUrls);
		$this->varnishProxyClient->setDefaultBanHeader('X-Site', $this->tokenStorage->getToken());
		$this->cacheInvalidator = new CacheInvalidator($this->varnishProxyClient);
		$this->tagHandler = new TagHandler($this->cacheInvalidator);
	}

	/**
	 * Clear all cache in Varnish for a optionally given domain & content type
	 *
	 * @param string $domain The domain to flush, e.g. "example.com"
	 * @param string $contentType The mime type to flush, e.g. "image/png"
	 * @return void
	 */
	public function banAll($domain = NULL, $contentType = NULL) {
		$this->cacheInvalidator->invalidateRegex('.*', $contentType, $domain);
		$this->logger->log(sprintf('Cleared all Varnish cache%s%s', $domain ? ' for domain "' . $domain . '"' : '', $contentType ? ' with content type "' . $contentType . '"' : ''));
		$this->execute();
	}

	/**
	 * Clear all cache in Varnish for given tags
	 *
	 * @param array $tags
	 * @param string $domain The domain to flush, e.g. "example.com"
	 * @return void
	 */
	public function banByTags(array $tags, $domain = NULL) {
		if (count($this->settings['ignoredCacheTags']) > 0) {
			$tags = array_diff($tags, $this->settings['ignoredCacheTags']);
		}
		// Set specific domain before invalidating tags
		if ($domain !== NULL) {
			$this->varnishProxyClient->setDefaultBanHeader(ProxyClient\Varnish::HTTP_HEADER_HOST, $domain);
		}
		$this->tagHandler->invalidateTags($tags);
		// Unset specific domain after invalidating tags
		if ($domain !== NULL) {
			$this->varnishProxyClient->setDefaultBanHeader(ProxyClient\Varnish::HTTP_HEADER_HOST, ProxyClient\Varnish::REGEX_MATCH_ALL);
		}
		$this->logger->log(sprintf('Cleared Varnish cache for tags "%s"%s', implode(',', $tags), $domain ? ' for domain "' . $domain . '"' : ''));
		$this->execute();
	}

	/**
	 * @return void
	 */
	protected function execute() {
		try {
			$this->cacheInvalidator->flush();
		} catch (ExceptionCollection $exceptions) {
			foreach ($exceptions as $exception) {
				if ($exception instanceof ProxyResponseException) {
					$this->logger->log(sprintf('Error calling Varnish with BAN request (cannot connect to the caching proxy). Error %s', $exception->getMessage()), LOG_ERR);
				} elseif ($exception instanceof ProxyUnreachableException) {
					$this->logger->log(sprintf('Error calling Varnish with BAN request (caching proxy returned an error response). Error %s', $exception->getMessage()), LOG_ERR);
				} else {
					$this->logger->log(sprintf('Error calling Varnish with BAN request. Error %s', $exception->getMessage()), LOG_ERR);
				}
			}
		}
	}

}
