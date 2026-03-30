<?php

declare(strict_types=1);

namespace TinyProxy\Cache;

use TinyProxy\Config\Configuration;
use TinyProxy\Util\FileHelper;

/**
 * Cache manager with size limits and eviction
 */
class CacheManager implements CacheInterface
{
    private int $maxSize;
    private int $hits = 0;
    private int $misses = 0;

    public function __construct(
        private readonly FileCache $cache,
        private readonly LRUEvictionStrategy $eviction,
        private readonly Configuration $config
    ) {
        $this->maxSize = $config->getInt('cache.max_size', 1073741824); // 1GB default
    }

    /**
     * Get content from cache
     */
    public function get(string $key): ?CachedContent
    {
        $content = $this->cache->get($key);

        if ($content === null) {
            $this->misses++;
            return null;
        }

        $this->hits++;
        $this->eviction->recordAccess($key);
        
        // Update access count
        $content->incrementAccessCount();
        
        return $content;
    }

    /**
     * Store content in cache
     */
    public function set(string $key, CachedContent $content): void
    {
        // Check if we need to evict entries
        $stats = $this->cache->getStats();
        $currentSize = $stats['size'];

        if ($currentSize + $content->getSize() > $this->maxSize) {
            $requiredSpace = ($currentSize + $content->getSize()) - $this->maxSize;
            $this->evictEntries($requiredSpace);
        }

        $this->cache->set($key, $content);
        $this->eviction->recordAccess($key);
    }

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * Delete entry from cache
     */
    public function delete(string $key): void
    {
        $this->cache->delete($key);
        $this->eviction->removeKey($key);
    }

    /**
     * Clear entire cache
     */
    public function clear(): void
    {
        $this->cache->clear();
        $this->eviction->clear();
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * Evict entries to free up space
     */
    private function evictEntries(int $requiredSpace): void
    {
        $keysToEvict = $this->eviction->getKeysToEvict($requiredSpace, $this->cache);

        foreach ($keysToEvict as $key) {
            $this->delete($key);
        }
    }

    /**
     * Clear cache entries older than specified seconds
     */
    public function clearOlderThan(int $seconds): int
    {
        $count = 0;
        $cutoff = time() - $seconds;
        $keys = $this->cache->getAllKeys();

        foreach ($keys as $key) {
            $content = $this->cache->get($key);
            if ($content && $content->getCreatedAt() < $cutoff) {
                $this->delete($key);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear cache entries matching pattern
     */
    public function clearByPattern(string $pattern): int
    {
        $count = 0;
        $keys = $this->cache->getAllKeys();

        foreach ($keys as $key) {
            if (fnmatch($pattern, $key)) {
                $this->delete($key);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        $stats = $this->cache->getStats();
        $totalRequests = $this->hits + $this->misses;
        $hitRate = $totalRequests > 0 ? ($this->hits / $totalRequests) * 100 : 0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => round($hitRate, 2),
            'total_requests' => $totalRequests,
            'entries' => $stats['count'],
            'size' => $stats['size'],
            'size_formatted' => $stats['size_formatted'],
            'max_size' => $this->maxSize,
            'max_size_formatted' => FileHelper::formatBytes($this->maxSize),
            'usage_percent' => round(($stats['size'] / $this->maxSize) * 100, 2),
        ];
    }

    /**
     * Get entry information
     */
    public function getEntryInfo(string $key): ?array
    {
        $content = $this->cache->get($key);
        
        if ($content === null) {
            return null;
        }

        return [
            'key' => $key,
            'content_type' => $content->getContentType(),
            'size' => $content->getSize(),
            'size_formatted' => FileHelper::formatBytes($content->getSize()),
            'created_at' => $content->getCreatedAt(),
            'last_accessed_at' => $content->getLastAccessedAt(),
            'access_count' => $content->getAccessCount(),
            'age' => time() - $content->getCreatedAt(),
        ];
    }

    /**
     * Get all cache entries with their info
     */
    public function getAllEntries(): array
    {
        $entries = [];
        $keys = $this->cache->getAllKeys();

        foreach ($keys as $key) {
            $info = $this->getEntryInfo($key);
            if ($info !== null) {
                $entries[] = $info;
            }
        }

        return $entries;
    }
}
