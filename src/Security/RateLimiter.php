<?php

declare(strict_types=1);

namespace TinyProxy\Security;

use TinyProxy\Config\Configuration;
use TinyProxy\Exception\SecurityException;

/**
 * Rate limiter using sliding window algorithm
 */
class RateLimiter
{
    private const KEY_PREFIX = 'rate_limit:';
    private bool $enabled;
    private int $requestsPerMinute;
    private int $requestsPerHour;
    private string $storage;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->enabled = $config->getBool('security.rate_limit_enabled', true);
        $this->requestsPerMinute = $config->getInt('security.rate_limit_per_minute', 60);
        $this->requestsPerHour = $config->getInt('security.rate_limit_per_hour', 1000);
        $this->storage = $config->getString('security.rate_limit_storage', 'apcu');
    }

    /**
     * Check if request is allowed for identifier
     */
    public function check(string $identifier): bool
    {
        if (!$this->enabled) {
            return true;
        }

        try {
            // Check per-minute limit
            if (!$this->checkLimit($identifier, 60, $this->requestsPerMinute)) {
                return false;
            }

            // Check per-hour limit
            if (!$this->checkLimit($identifier, 3600, $this->requestsPerHour)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If rate limiting fails, allow request (fail open)
            return true;
        }
    }

    /**
     * Alias for check() - Check if request is allowed
     */
    public function allowRequest(string $identifier): bool
    {
        return $this->check($identifier);
    }

    /**
     * Get current limits for an identifier
     * 
     * @return array{remaining_minute: int, remaining_hour: int, reset_in: int}
     */
    public function getLimits(string $identifier): array
    {
        return [
            'remaining_minute' => $this->getRemainingRequests($identifier, 60),
            'remaining_hour' => $this->getRemainingRequests($identifier, 3600),
            'reset_in' => $this->getResetTime($identifier, 60) - time(),
        ];
    }

    /**
     * Check and update rate limit for a specific window
     */
    private function checkLimit(string $identifier, int $window, int $limit): bool
    {
        $key = self::KEY_PREFIX . $identifier . ':' . $window;
        $now = time();

        if ($this->storage === 'apcu' && function_exists('apcu_enabled') && apcu_enabled()) {
            return $this->checkApcuLimit($key, $now, $window, $limit);
        }

        // Fallback to file-based storage
        return $this->checkFileLimit($key, $now, $window, $limit);
    }

    /**
     * Check limit using APCu
     */
    private function checkApcuLimit(string $key, int $now, int $window, int $limit): bool
    {
        $data = apcu_fetch($key);
        
        if ($data === false) {
            $data = [
                'count' => 0,
                'reset_at' => $now + $window,
            ];
        }

        // Reset if window has passed
        if ($now >= $data['reset_at']) {
            $data = [
                'count' => 0,
                'reset_at' => $now + $window,
            ];
        }

        $data['count']++;

        if ($data['count'] > $limit) {
            return false;
        }

        apcu_store($key, $data, $window);
        return true;
    }

    /**
     * Check limit using file-based storage
     */
    private function checkFileLimit(string $key, int $now, int $window, int $limit): bool
    {
        $dir = 'var/cache/rate_limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . md5($key);
        $data = null;

        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
            }
        }

        if ($data === null || $now >= ($data['reset_at'] ?? 0)) {
            $data = [
                'count' => 0,
                'reset_at' => $now + $window,
            ];
        }

        $data['count']++;

        if ($data['count'] > $limit) {
            return false;
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    /**
     * Get remaining requests for identifier
     */
    public function getRemainingRequests(string $identifier, int $window = 60): int
    {
        if (!$this->enabled) {
            return PHP_INT_MAX;
        }

        $limit = $window === 60 ? $this->requestsPerMinute : $this->requestsPerHour;
        $key = self::KEY_PREFIX . $identifier . ':' . $window;

        if ($this->storage === 'apcu' && function_exists('apcu_enabled') && apcu_enabled()) {
            $data = apcu_fetch($key);
        } else {
            $file = 'var/cache/rate_limit/' . md5($key);
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $data = $content !== false ? json_decode($content, true) : false;
            } else {
                $data = false;
            }
        }

        if ($data === false) {
            return $limit;
        }

        $now = time();
        if ($now >= ($data['reset_at'] ?? 0)) {
            return $limit;
        }

        return max(0, $limit - ($data['count'] ?? 0));
    }

    /**
     * Get reset time for identifier
     */
    public function getResetTime(string $identifier, int $window = 60): int
    {
        $key = self::KEY_PREFIX . $identifier . ':' . $window;

        if ($this->storage === 'apcu' && function_exists('apcu_enabled') && apcu_enabled()) {
            $data = apcu_fetch($key);
        } else {
            $file = 'var/cache/rate_limit/' . md5($key);
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $data = $content !== false ? json_decode($content, true) : false;
            } else {
                $data = false;
            }
        }

        if ($data === false) {
            return time() + $window;
        }

        return $data['reset_at'] ?? (time() + $window);
    }

    /**
     * Reset rate limit for identifier
     */
    public function reset(string $identifier): void
    {
        $key = self::KEY_PREFIX . $identifier . ':';
        
        if ($this->storage === 'apcu' && function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_delete($key . '60');
            apcu_delete($key . '3600');
        } else {
            @unlink('var/cache/rate_limit/' . md5($key . '60'));
            @unlink('var/cache/rate_limit/' . md5($key . '3600'));
        }
    }
}
