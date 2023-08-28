<?php

class Cache
{
    private $cacheDirectory = 'cache/';
    private $cacheDuration = 3600; // Cache duration in seconds (1 hour)

    public function has($key) {
        $filePath = $this->cacheDirectory . $key;
        return file_exists($filePath) && time() - filemtime($filePath) < $this->cacheDuration;
    }

    public function get($key) {
        if ($this->has($key)) {
            $filePath = $this->cacheDirectory . $key;
            return file_get_contents($filePath);
        }
        return false;
    }

    public function set($key, $content)
    {
        $filePath = $this->cacheDirectory . $key;

        file_put_contents($filePath, $content);
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
