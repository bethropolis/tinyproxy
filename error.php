<?php

class Logger {
    private $logFile = 'errors/error_log.txt' ;

    public function __construct($logFile = null) {
        if(!$logFile){
            return;
        }
        $this->logFile = $logFile;
    }

    public function log($message) {
        $timestamp = $this->getCurrentTimestamp();
        $formattedMessage = $this->formatMessage($timestamp, $message);
        $this->createLogFileIfNotExist();
        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
    }
    
    private function getCurrentTimestamp() {
        return date('[Y-m-d H:i:s]');
    }
    
    private function formatMessage($timestamp, $message) {
        return "$timestamp $message" . PHP_EOL;
    }
    
    private function createLogFileIfNotExist() {
        $logDirectory = dirname($this->logFile);
        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
    }
}
