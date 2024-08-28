<?php

class Cache
{
    private $cacheDirectory;
    private $cacheDuration;
    private $cachableTypes;

    public function __construct()
    {
        $this->cacheDirectory = CACHE_DIRECTORY;
        $this->cacheDuration = CACHE_DURATION;
        $this->cachableTypes = CACHABLE_TYPES;

    }

    public function has($key)
    {
        $filePath = $this->cacheDirectory . $key;
        return file_exists($filePath) && time() - filemtime($filePath) < $this->cacheDuration;
    }

    public function get($key)
    {
        if ($this->has($key) && $this->isCacheEnabled()) {
            $filePath = $this->cacheDirectory . $key;
            return file_get_contents($filePath);
        }
        return false;
    }

    public function set($key, $content)
    {
        if (!$this->isCacheEnabled()) return;

        if (!in_array($content['content_type'], $this->cachableTypes)) {
            return;
        }

        $content = json_encode($content);
        $filePath = $this->getFilePath($key);
        $this->createDirectory(dirname($filePath));
        $this->writeToFile($filePath, $content);
    }

    private function getFilePath($key)
    {
        return $this->cacheDirectory . $key;
    }

    private function createDirectory($directory)
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function writeToFile($filePath, $content)
    {
        file_put_contents($filePath, $content);
    }

    private function isCacheEnabled()
    {
        return CACHE_ENABLED;
    }

    public function clearCache()
    {
        // Clear all cached files
        $files = glob($this->cacheDirectory . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
