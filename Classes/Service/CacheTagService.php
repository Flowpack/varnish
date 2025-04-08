<?php
declare(strict_types=1);

namespace Flowpack\Varnish\Service;

use Neos\Flow\Annotations as Flow;

#[Flow\Scope("singleton")]
class CacheTagService
{

    /**
     * @var array
     * @Flow\InjectConfiguration(path="cacheHeaders")
     */
    protected $cacheHeaderConfiguration;

    /**
     * @param array<string> $tags
     * @return array
     * @see \Neos\Fusion\Core\Cache\ContentCache::sanitizeTags()
     *
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
     * * Flowpack.Varnish.cacheHeaders.shortenCacheTags
     * * Flowpack.Varnish.cacheHeaders.cacheTagLength
     *
     * @param array<string> $tags
     * @return array<string>
     */
    public function shortenTags(array $tags = []): array
    {
        if (!$this->shouldShortenTags()) {
            return $tags;
        }

        return array_map(function (string $tag) {
            return substr(md5($tag), 0, (int)($this->cacheHeaderConfiguration['cacheTagLength'] ?? 8));
        }, $tags);
    }

    protected function shouldShortenTags(): bool
    {
        return (bool)($this->cacheHeaderConfiguration['shortenCacheTags'] ?? false);
    }

}
