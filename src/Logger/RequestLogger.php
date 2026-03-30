<?php

declare(strict_types=1);

namespace TinyProxy\Logger;

use TinyProxy\Config\Configuration;
use TinyProxy\Util\FileHelper;

/**
 * Request logger for access logs
 */
class RequestLogger
{
    private string $logDirectory;
    private string $logFile;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->logDirectory = $config->getString('log.directory', 'var/logs');
        $this->logFile = $config->getString('log.access_file', 'access.log');
        
        FileHelper::ensureDirectory($this->logDirectory);
    }

    /**
     * Log an HTTP request
     */
    public function log(
        string $url,
        int $statusCode,
        float $duration,
        bool $cacheHit = false,
        ?string $ip = null,
        ?string $method = null,
        int $size = 0,
        ?string $userAgent = null,
        ?bool $cached = null
    ): void {
        if (!$this->config->getBool('log.enabled', true)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $cacheStatus = ($cached ?? $cacheHit) ? 'HIT' : 'MISS';
        $durationMs = round($duration * 1000, 2);
        $sizeStr = $size > 0 ? ' ' . $size . 'B' : '';

        $logLine = sprintf(
            "[%s] %s %s - %s %d%s %.2fms [%s] \"%s\"\n",
            $timestamp,
            $method ?? 'GET',
            $ip,
            $url,
            $statusCode,
            $sizeStr,
            $durationMs,
            $cacheStatus,
            $userAgent
        );

        $logPath = str_starts_with($this->logFile, '/') 
            ? $this->logFile 
            : $this->logDirectory . '/' . $this->logFile;
        file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
    }
}
