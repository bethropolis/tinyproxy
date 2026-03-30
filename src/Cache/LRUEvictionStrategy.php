<?php

declare(strict_types=1);

namespace TinyProxy\Cache;

use TinyProxy\Config\Configuration;
use TinyProxy\Util\FileHelper;

/**
 * LRU (Least Recently Used) cache eviction strategy
 */
class LRUEvictionStrategy
{
    private string $indexFile;
    private array $index = [];

    public function __construct(
        private readonly Configuration $config
    ) {
        $cacheDir = $config->getString('cache.directory', 'var/cache');
        $this->indexFile = $cacheDir . '/metadata/lru_index.json';
        $this->loadIndex();
    }

    /**
     * Load LRU index from file
     */
    private function loadIndex(): void
    {
        if (file_exists($this->indexFile)) {
            $content = file_get_contents($this->indexFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->index = $data;
                    return;
                }
            }
        }

        $this->index = [];
    }

    /**
     * Save LRU index to file
     */
    private function saveIndex(): void
    {
        FileHelper::put($this->indexFile, json_encode($this->index));
    }

    /**
     * Record access for a cache key
     */
    public function recordAccess(string $key): void
    {
        $this->index[$key] = time();
        $this->saveIndex();
    }

    /**
     * Remove key from index
     */
    public function removeKey(string $key): void
    {
        unset($this->index[$key]);
        $this->saveIndex();
    }

    /**
     * Get keys to evict to free up required space
     */
    public function getKeysToEvict(int $requiredSpace, FileCache $cache): array
    {
        // Get all keys sorted by last access time (ascending)
        asort($this->index);

        $keysToEvict = [];
        $freedSpace = 0;
        $maxSize = $this->config->getInt('CACHE_MAX_SIZE', 104857600); // 100MB default

        foreach (array_keys($this->index) as $key) {
            // Get content file size
            $cacheDir = $this->config->getString('CACHE_DIRECTORY', 'var/cache');
            $subdir = substr($key, 0, 2);
            $contentPath = $cacheDir . '/content/' . $subdir . '/' . $key;

            if (file_exists($contentPath)) {
                $size = filesize($contentPath);
                if ($size !== false) {
                    $freedSpace += $size;
                    $keysToEvict[] = $key;

                    // Check if we've freed enough space
                    if ($freedSpace >= $requiredSpace) {
                        break;
                    }
                }
            }
        }

        return $keysToEvict;
    }

    /**
     * Get least recently used keys
     */
    public function getLeastRecentlyUsed(int $count): array
    {
        asort($this->index);
        return array_slice(array_keys($this->index), 0, $count);
    }

    /**
     * Clear all index data
     */
    public function clear(): void
    {
        $this->index = [];
        $this->saveIndex();
    }
}
