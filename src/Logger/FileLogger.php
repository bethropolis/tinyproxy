<?php

declare(strict_types=1);

namespace TinyProxy\Logger;

use TinyProxy\Config\Configuration;
use TinyProxy\Util\FileHelper;

/**
 * File-based logger implementation
 */
class FileLogger implements LoggerInterface
{
    private const LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    private string $logDirectory;
    private string $logFile;
    private int $minLevel;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->logDirectory = $config->getString('log.directory', 'var/logs');
        $this->logFile = $config->getString('log.file', 'error.log');
        
        $minLevelName = $config->getString('log.level', 'info');
        $this->minLevel = self::LEVELS[strtolower($minLevelName)] ?? self::LEVELS['info'];

        FileHelper::ensureDirectory($this->logDirectory);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->config->getBool('log.enabled', true)) {
            return;
        }

        $levelValue = self::LEVELS[strtolower($level)] ?? self::LEVELS['info'];
        
        if ($levelValue > $this->minLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        // Use logFile directly if it's an absolute path, otherwise prepend directory
        $logPath = str_starts_with($this->logFile, '/') 
            ? $this->logFile 
            : $this->logDirectory . '/' . $this->logFile;
        
        file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
    }
}
