<?php

class Cache
{
    private $cacheDirectory = 'cache/';
    private $cacheDuration = 3600; // Cache duration in seconds (1 hour)

    public function has($key)
    {
        $filePath = $this->cacheDirectory . $key;
        return file_exists($filePath) && time() - filemtime($filePath) < $this->cacheDuration;
    }

    public function get($key)
    {
        if ($this->has($key)) {
            $filePath = $this->cacheDirectory . $key;
            return file_get_contents($filePath);
        }
        return false;
    }

    public function set($key, $content)
    {
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
