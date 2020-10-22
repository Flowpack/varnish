<?php
declare(strict_types=1);

namespace MOC\Varnish\Service;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class CacheTagService
{

    /**
     * @var int
     * @Flow\InjectConfiguration(path="cacheHeaders")
     */
    protected $cacheHeaderConfiguration;

    /**
     * @see \Neos\Fusion\Core\Cache\ContentCache::sanitizeTags()
     *
     * @param array $tags
     * @return array
     */
    public function sanitizeTags(array $tags): array
    {
        return array_map(function (string $tag) {
            return strtr($tag, '.:', '_-');
        }, $tags);
    }

    /**
     * Generate short md5 for cache tags if enabled
     *
     * See these two configuration options:
     * * MOC.Varnish.cacheHeaders.shortenCacheTags
     * * MOC.Varnish.cacheHeaders.cacheTagLength
     *
     * @param array $tags
     * @return array
     */
    public function shortenTags(array $tags = []): array
    {
        if (!$this->shouldShortenTags()) {
            return $tags;
        }

        return array_map(function (string $tag) {
            return substr(md5($tag), 0, (int)$this->cacheHeaderConfiguration['cacheTagLength'] ?? 8);
        }, $tags);
    }

    protected function shouldShortenTags(): bool
    {
        return (bool)$this->cacheHeaderConfiguration['shortenCacheTags'] ?? false;
    }

}
