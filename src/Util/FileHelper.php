<?php

declare(strict_types=1);

namespace TinyProxy\Util;

use RuntimeException;

/**
 * File system operations helper
 */
class FileHelper
{
    /**
     * Write content to file atomically
     */
    public static function put(string $path, string $content): void
    {
        $dir = dirname($path);
        
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
        }

        $tempFile = $path . '.' . uniqid('tmp', true);
        
        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write to temporary file: {$tempFile}");
        }

        if (!rename($tempFile, $path)) {
            @unlink($tempFile);
            throw new RuntimeException("Failed to move temporary file to: {$path}");
        }
    }

    /**
     * Read content from file
     */
    public static function get(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        return $content !== false ? $content : null;
    }

    /**
     * Delete file
     */
    public static function delete(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        return @unlink($path);
    }

    /**
     * Check if file exists and is readable
     */
    public static function exists(string $path): bool
    {
        return file_exists($path) && is_readable($path);
    }

    /**
     * Get file size in bytes
     */
    public static function size(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        $size = filesize($path);
        return $size !== false ? $size : 0;
    }

    /**
     * Get file modification time
     */
    public static function modifiedTime(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        $time = filemtime($path);
        return $time !== false ? $time : 0;
    }

    /**
     * Create directory if it doesn't exist
     */
    public static function ensureDirectory(string $path, int $permissions = 0755): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, $permissions, true) && !is_dir($path)) {
                throw new RuntimeException("Failed to create directory: {$path}");
            }
        }
    }

    /**
     * Delete directory recursively
     */
    public static function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);
        
        foreach ($files as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($fullPath)) {
                self::deleteDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        return rmdir($path);
    }

    /**
     * Get directory size in bytes
     */
    public static function directorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * List files in directory matching pattern
     */
    public static function glob(string $directory, string $pattern = '*'): array
    {
        $path = rtrim($directory, '/') . '/' . $pattern;
        return glob($path) ?: [];
    }

    /**
     * Format bytes to human-readable size
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Write content atomically (alias for put)
     */
    public static function writeAtomic(string $path, string $content): void
    {
        self::put($path, $content);
    }

    /**
     * Create directory (alias for ensureDirectory)
     */
    public static function createDirectory(string $path, int $permissions = 0755): void
    {
        self::ensureDirectory($path, $permissions);
    }
}
