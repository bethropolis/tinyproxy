<?php

declare(strict_types=1);

namespace TinyProxy\Statistics;

use TinyProxy\Config\Configuration;
use TinyProxy\Util\FileHelper;

/**
 * Collects and stores proxy statistics
 *
 * Tracks:
 * - Total requests
 * - Cache hits/misses
 * - Response times
 * - Error rates
 * - Bandwidth usage
 * - Top URLs
 * - Client IPs
 */
class StatsCollector
{
    private readonly string $statsFile;
    /** @var array<string, mixed> */
    private array $stats = [];
    private readonly int $maxTopItems;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->statsFile = $this->config->getString('stats.file', '/tmp/tinyproxy-stats.json');
        $this->maxTopItems = $this->config->getInt('stats.max_top_items', 100);
        $this->loadStats();
    }

    /**
     * Record a successful request
     */
    public function recordRequest(string $url, bool $cached, float $duration, string $clientIp): void
    {
        if (!$this->config->getBool('stats.enabled', true)) {
            return;
        }

        $this->stats['total_requests'] = ($this->stats['total_requests'] ?? 0) + 1;

        if ($cached) {
            $this->stats['cache_hits'] = ($this->stats['cache_hits'] ?? 0) + 1;
        } else {
            $this->stats['cache_misses'] = ($this->stats['cache_misses'] ?? 0) + 1;
        }

        // Track response times
        $this->stats['total_response_time'] = ($this->stats['total_response_time'] ?? 0.0) + $duration;
        $this->stats['avg_response_time'] = $this->stats['total_response_time'] / $this->stats['total_requests'];

        if (!isset($this->stats['min_response_time']) || $duration < $this->stats['min_response_time']) {
            $this->stats['min_response_time'] = $duration;
        }

        if (!isset($this->stats['max_response_time']) || $duration > $this->stats['max_response_time']) {
            $this->stats['max_response_time'] = $duration;
        }

        // Track top URLs
        $this->incrementCounter('top_urls', $url);

        // Track client IPs
        $this->incrementCounter('top_clients', $clientIp);

        // Update last request time
        $this->stats['last_request_time'] = time();

        $this->saveStats();
    }

    /**
     * Record an error
     */
    public function recordError(string $url, string $type, string $clientIp): void
    {
        if (!$this->config->getBool('stats.enabled', true)) {
            return;
        }

        $this->stats['total_errors'] = ($this->stats['total_errors'] ?? 0) + 1;
        $this->incrementCounter('errors_by_type', $type);
        $this->incrementCounter('errors_by_url', $url);

        $this->saveStats();
    }

    /**
     * Record bandwidth usage
     */
    public function recordBandwidth(int $bytes, bool $incoming): void
    {
        if (!$this->config->getBool('stats.enabled', true)) {
            return;
        }

        if ($incoming) {
            $this->stats['total_bytes_in'] = ($this->stats['total_bytes_in'] ?? 0) + $bytes;
        } else {
            $this->stats['total_bytes_out'] = ($this->stats['total_bytes_out'] ?? 0) + $bytes;
        }

        $this->saveStats();
    }

    /**
     * Get all statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $stats = $this->stats;

        // Calculate derived metrics
        $stats['cache_hit_rate'] = $this->calculateCacheHitRate();
        $stats['error_rate'] = $this->calculateErrorRate();
        $stats['uptime'] = $this->calculateUptime();

        // Sort top items by count
        if (isset($stats['top_urls'])) {
            arsort($stats['top_urls']);
            $stats['top_urls'] = array_slice($stats['top_urls'], 0, $this->maxTopItems, true);
        }

        if (isset($stats['top_clients'])) {
            arsort($stats['top_clients']);
            $stats['top_clients'] = array_slice($stats['top_clients'], 0, $this->maxTopItems, true);
        }

        return $stats;
    }

    /**
     * Reset all statistics
     */
    public function reset(): void
    {
        $this->stats = [
            'start_time' => time(),
            'total_requests' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'total_errors' => 0,
            'total_response_time' => 0.0,
            'total_bytes_in' => 0,
            'total_bytes_out' => 0,
        ];

        $this->saveStats();
    }

    /**
     * Get statistics for a specific time period
     *
     * @return array<string, mixed>
     */
    public function getStatsByPeriod(int $startTime, int $endTime): array
    {
        // For now, return current stats
        // Future enhancement: Store time-series data
        return $this->getStats();
    }

    /**
     * Increment a counter in a nested array
     */
    private function incrementCounter(string $key, string $item): void
    {
        if (!isset($this->stats[$key])) {
            $this->stats[$key] = [];
        }

        if (!isset($this->stats[$key][$item])) {
            $this->stats[$key][$item] = 0;
        }

        $this->stats[$key][$item]++;

        // Trim to max items if exceeded
        if (count($this->stats[$key]) > $this->maxTopItems * 2) {
            arsort($this->stats[$key]);
            $this->stats[$key] = array_slice($this->stats[$key], 0, $this->maxTopItems, true);
        }
    }

    /**
     * Calculate cache hit rate percentage
     */
    private function calculateCacheHitRate(): float
    {
        $total = ($this->stats['cache_hits'] ?? 0) + ($this->stats['cache_misses'] ?? 0);
        if ($total === 0) {
            return 0.0;
        }

        return round((($this->stats['cache_hits'] ?? 0) / $total) * 100, 2);
    }

    /**
     * Calculate error rate percentage
     */
    private function calculateErrorRate(): float
    {
        $total = ($this->stats['total_requests'] ?? 0) + ($this->stats['total_errors'] ?? 0);
        if ($total === 0) {
            return 0.0;
        }

        return round((($this->stats['total_errors'] ?? 0) / $total) * 100, 2);
    }

    /**
     * Calculate uptime in seconds
     */
    private function calculateUptime(): int
    {
        $startTime = $this->stats['start_time'] ?? time();
        return time() - $startTime;
    }

    /**
     * Load statistics from file
     */
    private function loadStats(): void
    {
        if (!file_exists($this->statsFile)) {
            $this->reset();
            return;
        }

        $content = file_get_contents($this->statsFile);
        if ($content === false) {
            $this->reset();
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            $this->reset();
            return;
        }

        $this->stats = $data;

        // Ensure start_time exists
        if (!isset($this->stats['start_time'])) {
            $this->stats['start_time'] = time();
        }
    }

    /**
     * Save statistics to file
     */
    private function saveStats(): void
    {
        $dir = dirname($this->statsFile);
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir);
        }

        FileHelper::writeAtomic(
            $this->statsFile,
            json_encode($this->stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
