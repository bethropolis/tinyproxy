<?php

declare(strict_types=1);

namespace TinyProxy\Cache;

use TinyProxy\Config\Configuration;
use TinyProxy\Exception\CacheException;
use TinyProxy\Util\FileHelper;

/**
 * File-based cache implementation with compression
 */
class FileCache implements CacheInterface
{
    private string $cacheDirectory;
    private int $cacheDuration;
    private array $cachableTypes;
    private bool $compressionEnabled;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->cacheDirectory = $config->getString('cache.directory', 'var/cache');
        $this->cacheDuration = $config->getInt('cache.default_ttl', 3600);
        $this->cachableTypes = $config->getArray('cache.cachable_types', [
            'text/javascript',
            'text/css',
            'text/html',
            'application/json',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg+xml'
        ]);
        $this->compressionEnabled = $config->getBool('cache.compression', true);

        FileHelper::ensureDirectory($this->cacheDirectory . '/content');
        FileHelper::ensureDirectory($this->cacheDirectory . '/metadata');
    }

    public function get(string $key): ?CachedContent
    {
        $contentPath = $this->getContentPath($key);
        $metadataPath = $this->getMetadataPath($key);

        if (!file_exists($contentPath) || !file_exists($metadataPath)) {
            return null;
        }

        // Load metadata
        $metadataJson = file_get_contents($metadataPath);
        if ($metadataJson === false) {
            return null;
        }

        $metadata = json_decode($metadataJson, true);
        if ($metadata === null) {
            return null;
        }

        // Check if expired
        if (time() - $metadata['created_at'] > $this->getTtl($metadata['content_type'])) {
            $this->delete($key);
            return null;
        }

        // Load content
        $content = file_get_contents($contentPath);
        if ($content === false) {
            return null;
        }

        // Decompress if needed
        if ($this->compressionEnabled && ($metadata['compressed'] ?? false)) {
            $content = gzdecode($content);
            if ($content === false) {
                return null;
            }
        }

        $metadata['content'] = $content;
        
        return CachedContent::fromArray($metadata);
    }

    public function set(string $key, CachedContent $content): void
    {
        if (!$this->config->getBool('cache.enabled', true)) {
            return;
        }

        // Check if content type is cachable
        if (!in_array($content->getContentType(), $this->cachableTypes, true)) {
            return;
        }

        $contentData = $content->getContent();
        $compressed = false;

        // Compress content if enabled
        if ($this->compressionEnabled) {
            $compressed = gzencode($contentData, 9);
            if ($compressed !== false) {
                $contentData = $compressed;
                $compressed = true;
            }
        }

        // Save content
        $contentPath = $this->getContentPath($key);
        FileHelper::put($contentPath, $contentData);

        // Save metadata
        $metadata = $content->toArray();
        unset($metadata['content']); // Don't store content in metadata
        $metadata['compressed'] = $compressed;

        $metadataPath = $this->getMetadataPath($key);
        FileHelper::put($metadataPath, json_encode($metadata));
    }

    public function has(string $key): bool
    {
        $contentPath = $this->getContentPath($key);
        $metadataPath = $this->getMetadataPath($key);

        if (!file_exists($contentPath) || !file_exists($metadataPath)) {
            return false;
        }

        // Check if expired
        $metadataJson = file_get_contents($metadataPath);
        if ($metadataJson === false) {
            return false;
        }

        $metadata = json_decode($metadataJson, true);
        if ($metadata === null) {
            return false;
        }

        if (time() - $metadata['created_at'] > $this->getTtl($metadata['content_type'])) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): void
    {
        $contentPath = $this->getContentPath($key);
        $metadataPath = $this->getMetadataPath($key);

        FileHelper::delete($contentPath);
        FileHelper::delete($metadataPath);
    }

    public function clear(): void
    {
        $contentDir = $this->cacheDirectory . '/content';
        $metadataDir = $this->cacheDirectory . '/metadata';

        // Delete all files in content directory
        $files = FileHelper::glob($contentDir, '*/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        // Delete all files in metadata directory
        $files = FileHelper::glob($metadataDir, '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Generate a safe cache key from the URL
     */
    private function getSafeKey(string $key): string
    {
        // Hash the key to get a safe filename
        return hash('sha256', $key);
    }

    /**
     * Get file path for content
     */
    private function getContentPath(string $key): string
    {
        $safeKey = $this->getSafeKey($key);
        // Use first 2 characters of hash for directory sharding
        $subdir = substr($safeKey, 0, 2);
        $dir = $this->cacheDirectory . '/content/' . $subdir;
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/' . $safeKey;
    }

    /**
     * Get file path for metadata
     */
    private function getMetadataPath(string $key): string
    {
        $safeKey = $this->getSafeKey($key);
        return $this->cacheDirectory . '/metadata/' . $safeKey . '.meta';
    }

    /**
     * Get TTL for content type
     */
    private function getTtl(string $contentType): int
    {
        // Could be configured per content type
        return $this->cacheDuration;
    }

    /**
     * Get all cache keys
     */
    public function getAllKeys(): array
    {
        $keys = [];
        $metadataFiles = FileHelper::glob($this->cacheDirectory . '/metadata', '*.meta');
        
        foreach ($metadataFiles as $file) {
            $filename = basename($file, '.meta');
            $keys[] = $filename;
        }

        return $keys;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $contentDir = $this->cacheDirectory . '/content';
        $size = FileHelper::directorySize($contentDir);
        $keys = $this->getAllKeys();

        return [
            'count' => count($keys),
            'size' => $size,
            'size_formatted' => FileHelper::formatBytes($size),
        ];
    }
}
