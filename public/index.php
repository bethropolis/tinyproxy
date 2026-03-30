<?php

declare(strict_types=1);

/**
 * TinyProxy - Modern PHP Proxy Server
 * 
 * Entry point for all HTTP requests
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Bootstrap the application
$container = \TinyProxy\Bootstrap::boot(BASE_PATH);

// Create and run the application
$app = new \TinyProxy\Core\Application(
    $container,
    $container->get(\TinyProxy\Config\Configuration::class),
    $container->get(\TinyProxy\Core\ProxyService::class),
    $container->get(\TinyProxy\Logger\LoggerInterface::class)
);

$app->run();
