<?php

declare(strict_types=1);

/**
 * TinyProxy Configuration
 * 
 * This file contains default configuration values.
 * Override these by setting environment variables in .env file.
 */

return [
    // Application settings
    'app' => [
        'name' => getenv('APP_NAME') ?: 'TinyProxy',
        'version' => '2.0.0',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    ],

    // Proxy settings
    'proxy' => [
        'base_url' => getenv('PROXY_BASE_URL') ?: '',
    ],

    // Cache settings
    'cache' => [
        'enabled' => filter_var(getenv('CACHE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'directory' => getenv('CACHE_DIR') ?: BASE_PATH . '/var/cache',
        'default_ttl' => (int) (getenv('CACHE_DEFAULT_TTL') ?: 3600),
        'max_size' => (int) (getenv('CACHE_MAX_SIZE') ?: 1073741824), // 1GB
        'max_entry_size' => (int) (getenv('CACHE_MAX_ENTRY_SIZE') ?: 10485760), // 10MB
        'compression' => filter_var(getenv('CACHE_COMPRESSION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'cachable_types' => ['text/javascript', 'text/css', 'text/html', 'application/json', 'text/plain', 'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'],
    ],

    // Logging settings
    'log' => [
        'enabled' => filter_var(getenv('LOG_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'directory' => getenv('LOG_DIR') ?: BASE_PATH . '/var/logs',
        'file' => getenv('LOG_FILE') ?: BASE_PATH . '/var/logs/tinyproxy.log',
        'access_file' => getenv('LOG_ACCESS_FILE') ?: BASE_PATH . '/var/logs/access.log',
        'level' => getenv('LOG_LEVEL') ?: 'info',
    ],

    // Statistics settings
    'stats' => [
        'enabled' => filter_var(getenv('STATS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'file' => getenv('STATS_FILE') ?: BASE_PATH . '/var/stats.json',
        'max_top_items' => (int) (getenv('STATS_MAX_TOP_ITEMS') ?: 100),
    ],

    // Security settings
    'security' => [
        'require_auth' => filter_var(getenv('SECURITY_REQUIRE_AUTH') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'require_api_key' => filter_var(getenv('SECURITY_REQUIRE_API_KEY') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'api_keys' => array_filter(explode(',', getenv('SECURITY_API_KEYS') ?: '')),
        'ip_whitelist' => array_filter(explode(',', getenv('SECURITY_IP_WHITELIST') ?: '')),
        'ip_blacklist' => array_filter(explode(',', getenv('SECURITY_IP_BLACKLIST') ?: '')),
        'url_whitelist' => array_filter(explode(',', getenv('SECURITY_URL_WHITELIST') ?: '')),
        'url_blacklist' => array_filter(explode(',', getenv('SECURITY_URL_BLACKLIST') ?: '')),
        'block_private_ips' => filter_var(getenv('SECURITY_BLOCK_PRIVATE_IPS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'block_local_ips' => filter_var(getenv('SECURITY_BLOCK_LOCAL_IPS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'block_metadata' => filter_var(getenv('SECURITY_BLOCK_METADATA') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'rate_limit_enabled' => filter_var(getenv('SECURITY_RATE_LIMIT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'rate_limit_per_minute' => (int) (getenv('SECURITY_RATE_LIMIT_PER_MINUTE') ?: 60),
        'rate_limit_per_hour' => (int) (getenv('SECURITY_RATE_LIMIT_PER_HOUR') ?: 1000),
        'rate_limit_storage' => getenv('SECURITY_RATE_LIMIT_STORAGE') ?: 'apcu',
        'jwt_secret' => getenv('SECURITY_JWT_SECRET') ?: 'changeme',
        'jwt_algorithm' => getenv('SECURITY_JWT_ALGORITHM') ?: 'HS256',
    ],

    // HTTP client settings
    'http' => [
        'timeout' => (int) (getenv('HTTP_TIMEOUT') ?: 30),
        'connect_timeout' => (int) (getenv('HTTP_CONNECT_TIMEOUT') ?: 10),
        'verify_ssl' => filter_var(getenv('HTTP_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'max_redirects' => (int) (getenv('HTTP_MAX_REDIRECTS') ?: 5),
        'user_agent' => getenv('HTTP_USER_AGENT') ?: 'TinyProxy/2.0',
    ],

    // Content modifier settings
    'modifiers' => [
        'html' => [
            'enabled' => filter_var(getenv('MODIFIER_HTML_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'rewrite_urls' => filter_var(getenv('MODIFIER_HTML_REWRITE_URLS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'add_navigation' => filter_var(getenv('MODIFIER_HTML_ADD_NAVIGATION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'url_attributes' => ['href', 'src', 'action'],
            'show_top_bar' => filter_var(getenv('MODIFIER_HTML_SHOW_TOP_BAR') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        ],
        'css' => [
            'enabled' => filter_var(getenv('MODIFIER_CSS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        ],
        'adblock' => [
            'enabled' => filter_var(getenv('MODIFIER_ADBLOCK_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        ],
    ],
];
