<?php

class Logger
{
    private $logDirectory;
    private $logFile;

    public function __construct()
    {
        $this->logFile = ERROR_LOG_FILE;
        $this->logDirectory = ERROR_LOG_DIRECTORY;
    }

    public function log($message)
    {
        if (!ERROR_LOG_ENABLED) return;

        $fileLocation = $this->logDirectory . $this->logFile;
        $timestamp = $this->getCurrentTimestamp();
        $formattedMessage = $this->formatMessage($timestamp, $message);
        $this->createLogDirectoryIfNotExist();

        file_put_contents($fileLocation, $formattedMessage, FILE_APPEND);
    }

    private function getCurrentTimestamp()
    {
        return date('[Y-m-d H:i:s]');
    }

    private function formatMessage($timestamp, $message)
    {
        return "$timestamp - $message" . PHP_EOL;
    }

    private function createLogDirectoryIfNotExist()
    {
        if (!file_exists($this->logDirectory)) {
            mkdir($this->logDirectory, 0777, true);
        }
    }
}
