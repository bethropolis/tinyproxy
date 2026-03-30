<?php

declare(strict_types=1);

namespace TinyProxy;

use Dotenv\Dotenv;
use TinyProxy\Cache\CacheManager;
use TinyProxy\Cache\FileCache;
use TinyProxy\Cache\LRUEvictionStrategy;
use TinyProxy\Config\Configuration;
use TinyProxy\Container;
use TinyProxy\Core\ProxyService;
use TinyProxy\Http\Client;
use TinyProxy\Logger\FileLogger;
use TinyProxy\Logger\LoggerInterface;
use TinyProxy\Logger\RequestLogger;
use TinyProxy\Modifier\AdBlocker;
use TinyProxy\Modifier\CssModifier;
use TinyProxy\Modifier\HtmlModifier;
use TinyProxy\Security\AccessControl;
use TinyProxy\Security\RateLimiter;
use TinyProxy\Security\UrlValidator;
use TinyProxy\Statistics\StatsCollector;

/**
 * Application bootstrapper
 *
 * Responsible for:
 * - Loading environment variables
 * - Setting up configuration
 * - Registering services in the DI container
 * - Initializing the application
 */
class Bootstrap
{
    private static ?Container $container = null;

    /**
     * Bootstrap the application
     */
    public static function boot(string $basePath): Container
    {
        if (self::$container !== null) {
            return self::$container;
        }

        // Load environment variables
        self::loadEnvironment($basePath);

        // Create DI container
        $container = new Container();

        // Register configuration
        self::registerConfiguration($container, $basePath);

        // Register core services
        self::registerLogger($container);
        self::registerSecurity($container);
        self::registerCache($container);
        self::registerHttp($container);
        self::registerModifiers($container);
        self::registerStatistics($container);
        self::registerProxyService($container);

        self::$container = $container;

        return $container;
    }

    /**
     * Get the container instance
     */
    public static function getContainer(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException('Application not bootstrapped. Call Bootstrap::boot() first.');
        }

        return self::$container;
    }

    /**
     * Load environment variables from .env file
     */
    private static function loadEnvironment(string $basePath): void
    {
        $envFile = $basePath . '/.env';

        if (!file_exists($envFile)) {
            // .env is optional, use defaults
            return;
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();
    }

    /**
     * Register configuration service
     */
    private static function registerConfiguration(Container $container, string $basePath): void
    {
        $container->singleton(Configuration::class, function () use ($basePath) {
            return new Configuration($basePath);
        });
    }

    /**
     * Register logging services
     */
    private static function registerLogger(Container $container): void
    {
        $container->singleton(LoggerInterface::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new FileLogger($config);
        });

        $container->singleton(RequestLogger::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new RequestLogger($config);
        });
    }

    /**
     * Register security services
     */
    private static function registerSecurity(Container $container): void
    {
        $container->singleton(UrlValidator::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new UrlValidator($config);
        });

        $container->singleton(RateLimiter::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new RateLimiter($config);
        });

        $container->singleton(AccessControl::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new AccessControl($config);
        });
    }

    /**
     * Register cache services
     */
    private static function registerCache(Container $container): void
    {
        $container->singleton(FileCache::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new FileCache($config);
        });

        $container->singleton(CacheManager::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            $fileCache = $c->get(FileCache::class);

            return new CacheManager(
                $fileCache,
                new LRUEvictionStrategy($config),
                $config
            );
        });

        // Alias CacheInterface to CacheManager
        $container->singleton(\TinyProxy\Cache\CacheInterface::class, function (Container $c) {
            return $c->get(CacheManager::class);
        });
    }

    /**
     * Register HTTP client
     */
    private static function registerHttp(Container $container): void
    {
        $container->singleton(Client::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            $urlValidator = $c->get(UrlValidator::class);
            $logger = $c->get(LoggerInterface::class);

            return new Client($urlValidator, $config, $logger);
        });
    }

    /**
     * Register content modifiers
     */
    private static function registerModifiers(Container $container): void
    {
        $container->singleton('modifiers', function (Container $c) {
            $config = $c->get(Configuration::class);
            $modifiers = [];

            // CSS modifier (needed by HTML modifier)
            $cssModifier = new CssModifier($config);

            // HTML modifier
            if ($config->getBool('modifiers.html.enabled', true)) {
                $modifiers[] = new HtmlModifier($config, $cssModifier, new AdBlocker($config));
            }

            // CSS modifier
            if ($config->getBool('modifiers.css.enabled', true)) {
                $modifiers[] = $cssModifier;
            }

            // Ad blocker (standalone, if not already included in HTML modifier)
            if ($config->getBool('modifiers.adblock.enabled', false) && !$config->getBool('modifiers.html.enabled', true)) {
                $modifiers[] = new AdBlocker($config);
            }

            return $modifiers;
        });
    }

    /**
     * Register statistics service
     */
    private static function registerStatistics(Container $container): void
    {
        $container->singleton(StatsCollector::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new StatsCollector($config);
        });
    }

    /**
     * Register proxy service
     */
    private static function registerProxyService(Container $container): void
    {
        $container->singleton(ProxyService::class, function (Container $c) {
            return new ProxyService(
                $c->get(Configuration::class),
                $c->get(UrlValidator::class),
                $c->get(RateLimiter::class),
                $c->get(AccessControl::class),
                $c->get(\TinyProxy\Cache\CacheInterface::class),
                $c->get(Client::class),
                $c->get('modifiers'),
                $c->get(StatsCollector::class),
                $c->get(LoggerInterface::class),
                $c->get(RequestLogger::class)
            );
        });
    }
}
