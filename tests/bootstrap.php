<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Set test environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['CACHE_ENABLED'] = 'false';
$_ENV['LOG_ENABLED'] = 'false';
