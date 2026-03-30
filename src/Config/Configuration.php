<?php

declare(strict_types=1);

namespace TinyProxy\Config;

use TinyProxy\Exception\ConfigException;
use TinyProxy\Util\TimeHelper;

/**
 * Application configuration management
 */
class Configuration
{
    private array $config = [];
    private bool $loaded = false;

    public function __construct(
        private readonly string $basePath
    ) {
        $this->load();
    }

    /**
     * Load configuration from environment and files
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        // Load from $_ENV (populated by dotenv or system)
        $this->config = $_ENV;

        // Load from config files if they exist
        $configFile = $this->basePath . '/config/config.php';
        if (file_exists($configFile)) {
            $fileConfig = require $configFile;
            if (is_array($fileConfig)) {
                $this->config = array_merge($this->config, $fileConfig);
            }
        }

        $this->loaded = true;
    }

    /**
     * Get configuration value
     * Supports dot notation for nested keys (e.g., 'cache.enabled')
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Try flat key first (for ENV vars)
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        // Try dot notation for nested arrays
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Get string configuration value
     */
    public function getString(string $key, ?string $default = null): string
    {
        $value = $this->get($key, $default);
        
        if ($value === null) {
            throw new ConfigException("Configuration key '{$key}' is not set");
        }

        return (string) $value;
    }

    /**
     * Get integer configuration value
     */
    public function getInt(string $key, ?int $default = null): int
    {
        $value = $this->get($key, $default);
        
        if ($value === null) {
            throw new ConfigException("Configuration key '{$key}' is not set");
        }

        return (int) $value;
    }

    /**
     * Get boolean configuration value
     */
    public function getBool(string $key, ?bool $default = null): bool
    {
        $value = $this->get($key, $default);
        
        if ($value === null) {
            throw new ConfigException("Configuration key '{$key}' is not set");
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get array configuration value (comma-separated strings are converted to arrays)
     */
    public function getArray(string $key, ?array $default = null): array
    {
        $value = $this->get($key, $default);
        
        if ($value === null) {
            throw new ConfigException("Configuration key '{$key}' is not set");
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }

        return (array) $value;
    }

    /**
     * Get time duration in seconds (supports human-readable format like "1h", "30m")
     */
    public function getDuration(string $key, ?int $default = null): int
    {
        $value = $this->get($key, $default);
        
        if ($value === null) {
            throw new ConfigException("Configuration key '{$key}' is not set");
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return TimeHelper::parse((string) $value);
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * Set configuration value (for testing purposes)
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Check if running in production environment
     */
    public function isProduction(): bool
    {
        return $this->getString('APP_ENV', 'production') === 'production';
    }

    /**
     * Check if running in development environment
     */
    public function isDevelopment(): bool
    {
        return $this->getString('APP_ENV', 'production') === 'development';
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebug(): bool
    {
        return $this->getBool('APP_DEBUG', false);
    }
}
