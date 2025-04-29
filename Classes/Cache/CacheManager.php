<?php

declare(strict_types=1);
namespace Sypets\Brofix\Cache;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

class CacheManager
{
    // 1h
    final public const CACHE_DURATION_DEFAULT = 3600;

    /**
     * TYPO3 Frontend Cache object for zsb.
     * @var FrontendInterface
     */
    protected $cache;

    /**
     * Cache duration in seconds
     *
     * @var int
     */
    protected $lifetime = self::CACHE_DURATION_DEFAULT;

    public function __construct(FrontendInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Get the (unserialized) Cache object for index.
     *
     * If the query parameter contains refresh-cache or SHIFT-reload is
     *   pressed, do NOT load from cache!
     *
     * @param string $id Identifier
     * @return array<mixed>|null
     *
     * @todo use json_encode / json_decode instead of unserialize / serialize
     */
    public function getObject(string $id): ?array
    {
        $obj = $this->cache->get($id);
        if ($obj !== false) {
            $obj = unserialize($obj, ['allowed_classes' => false]);
        }
        return $obj ?: null;
    }

    /**
     * Serialize an object and put it in the cache.
     *
     * @param string $id Identifier
     * @param array<mixed> $content
     * @param int $lifetime Lifetime in seconds (7200 = 2h is default, 0 means default)
     * @param array<int,string> $cacheTags cache tags, 'studip' will always be added
     */
    public function setObject(
        string $id,
        array $content,
        int $lifetime = 7200,
        array $cacheTags = []
    ): void {
        if ($lifetime === 0) {
            $lifetime = $this->lifetime ?: self::CACHE_DURATION_DEFAULT;
        }
        $cacheTags[] = 'brofix';
        $cacheTags = $this->normalizeCacheTags($cacheTags);
        $this->cache->set($id, serialize($content), $cacheTags, $lifetime);
    }

    public function flushByTag(string $tag): void
    {
        $this->cache->flushByTag($tag);
    }

    /**
     * @param array<int,string> $cacheTags
     * @return array<int,string>
     */
    public function normalizeCacheTags(array $cacheTags): array
    {
        if (!$cacheTags) {
            return [];
        }

        foreach ($cacheTags as $key => $value) {
            $cacheTags[$key] = $this->normalizeCacheTag($value);
        }
        return $cacheTags;
    }

    public function normalizeCacheTag(string $tag): string
    {
        if ($this->cache->isValidTag($tag)) {
            return $tag;
        }

        // todo: create patch in TYPO3 core to add function to normalize tag (and identifier) in AbstractFrontend
        // see FrontendInterface::PATTERN_TAG = '/^[a-zA-Z0-9_%\\-&]{1,250}$/'
        $tag = preg_replace('#[^a-zA-Z0-9_%\\-&]#', '_', $tag);
        return $tag;
    }
}
