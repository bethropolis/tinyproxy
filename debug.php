<?php
class DebugLogger {
    public static function logToFile(...$args) {
        $timestamp = date("Y-m-d H:i:s");
        $logMessage = "[" . $timestamp . "] " . implode(" ", $args) . "\n";

        if (!file_exists(DEBUG_DIRECTORY)) {
            mkdir(DEBUG_DIRECTORY, 0777, true);
        }

        file_put_contents(DEBUG_DIRECTORY . DEBUG_FILE, $logMessage, FILE_APPEND);
    }
    public static function dump(...$args) {
        $dump = var_export($args, true);
        self::logToFile($dump);
        echo "<pre>$dump</pre>";
    }
    public static function logBacktrace() {
        ob_start();
        debug_print_backtrace();
        $backtrace = ob_get_clean();
        self::logToFile($backtrace);
        echo "<pre>$backtrace</pre>";
    }
}

