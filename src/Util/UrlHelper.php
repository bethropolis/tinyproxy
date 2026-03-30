<?php

declare(strict_types=1);

namespace TinyProxy\Util;

use InvalidArgumentException;

/**
 * URL manipulation and validation helper
 */
class UrlHelper
{
    /**
     * Convert a relative URL to an absolute URL
     */
    public static function makeAbsolute(string $relativeUrl, string $baseUrl): string
    {
        $parsedBase = parse_url($baseUrl);
        $parsedRelative = parse_url($relativeUrl);

        if (!$parsedBase) {
            throw new InvalidArgumentException("Invalid base URL: {$baseUrl}");
        }

        // If relative URL is already absolute, return it
        if (isset($parsedRelative['scheme'])) {
            return $relativeUrl;
        }

        // Handle protocol-relative URLs (//example.com)
        if (str_starts_with($relativeUrl, '//')) {
            return ($parsedBase['scheme'] ?? 'https') . ':' . $relativeUrl;
        }

        // Build base components
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        $port = isset($parsedBase['port']) ? ':' . $parsedBase['port'] : '';

        // Handle empty or fragment-only URLs
        if (empty($relativeUrl) || str_starts_with($relativeUrl, '#')) {
            return "{$scheme}://{$host}{$port}" . ($parsedBase['path'] ?? '/') . $relativeUrl;
        }

        // Handle query-only URLs
        if (str_starts_with($relativeUrl, '?')) {
            return "{$scheme}://{$host}{$port}" . ($parsedBase['path'] ?? '/') . $relativeUrl;
        }

        // Handle root-relative URLs (/path)
        if (str_starts_with($relativeUrl, '/')) {
            return "{$scheme}://{$host}{$port}{$relativeUrl}";
        }

        // Handle relative paths
        $basePath = $parsedBase['path'] ?? '/';
        $basePathParts = explode('/', rtrim($basePath, '/'));
        array_pop($basePathParts); // Remove filename

        $relativePathParts = explode('/', $relativeUrl);

        foreach ($relativePathParts as $part) {
            if ($part === '..') {
                array_pop($basePathParts);
            } elseif ($part !== '.' && $part !== '') {
                $basePathParts[] = $part;
            }
        }

        $path = implode('/', $basePathParts);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }

    /**
     * Check if a URL is absolute
     */
    public static function isAbsolute(string $url): bool
    {
        $parsed = parse_url($url);
        return isset($parsed['scheme']) && isset($parsed['host']);
    }

    /**
     * Get domain from URL
     */
    public static function getDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    /**
     * Check if URL matches a pattern (supports wildcards)
     */
    public static function matchesPattern(string $url, string $pattern): bool
    {
        $pattern = str_replace(['*', '?'], ['.*', '.'], $pattern);
        $pattern = '#^' . $pattern . '$#i';
        return (bool) preg_match($pattern, $url);
    }

    /**
     * Extract IP address from URL
     */
    public static function extractIp(string $url): ?string
    {
        $host = self::getDomain($url);
        if (!$host) {
            return null;
        }

        // Check if host is already an IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        return null;
    }

    /**
     * Normalize URL (remove trailing slash, lowercase domain, etc.)
     */
    public static function normalize(string $url): string
    {
        $parsed = parse_url($url);
        
        if (!$parsed) {
            return $url;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host'] ?? '');
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        // Remove default ports
        if (($scheme === 'http' && $port === ':80') || ($scheme === 'https' && $port === ':443')) {
            $port = '';
        }

        // Remove trailing slash from path (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}";
    }
}
