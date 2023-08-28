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
        $timestamp = date('[Y-m-d H:i:s]');
        $formattedMessage = "$timestamp $message" . PHP_EOL;

        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
    }
}
