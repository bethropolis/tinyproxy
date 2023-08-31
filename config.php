<?php

$protocol = $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'];

// proxy settings
if (!defined('PROXY_ENABLED'))  define('PROXY_ENABLED', true);

if (!defined('PROXY_HOST'))  define('PROXY_HOST', $host);

if(!defined("PROXY_ALLOWED_ORIGINS"))  define("PROXY_ALLOWED_ORIGINS", ['*']);

if(!defined('PROXY_USE_CSP'))  define('PROXY_USE_CSP', true);


// proxy url
if (!defined('PROXY_URL'))  define('PROXY_URL', $protocol . '://' . PROXY_HOST);

if(!defined('PROXY_BASE_URL'))  define('PROXY_BASE_URL', PROXY_URL . dirname($_SERVER['PHP_SELF']));

if(!defined('PROXY_URL_QUERY_KEY'))  define('PROXY_URL_QUERY_KEY', "url");

if (!defined('PROXY_REQUEST_URL'))  define('PROXY_REQUEST_URL', PROXY_BASE_URL . "?".PROXY_URL_QUERY_KEY."=");

if(!defined('PROXY_CURRENT_URL'))  define('PROXY_CURRENT_URL', PROXY_URL. $_SERVER['REQUEST_URI']);


// cache settings
if (!defined('CACHE_ENABLED'))  define('CACHE_ENABLED', true);

if (!defined('CACHE_DIRECTORY'))  define('CACHE_DIRECTORY', 'cache/');

if (!defined('CACHE_DURATION'))  define('CACHE_DURATION', 3600);

if (!defined('CACHABLE_TYPES'))  define('CACHABLE_TYPES', [
    'text/javascript',
    'text/css',
    'text/html',
    'application/json',
    'text/plain'
]);

if(!defined('CACHE_IMAGES'))  define('CACHE_IMAGES', true);

if(!defined('CACHE_MAX_AGE_HEADER'))  define('CACHE_MAX_AGE_HEADER', 7200);

if(!defined('CACHE_MAX_SIZE'))  define('CACHE_MAX_SIZE', (6 * 1024 * 1024));

// html/css modifier settings
if(!defined('HTML_MODIFIER_ENABLED'))  define('HTML_MODIFIER_ENABLED', true);

if(!defined('CSS_MODIFIER_ENABLED'))  define('CSS_MODIFIER_ENABLED', true);

if(!defined('HTML_MODIFIER_URL_ATTRIBUTES'))  define('HTML_MODIFIER_URL_ATTRIBUTES', [
    'href',
    'src',
    'action'
]);

if(!defined('HTML_MODIFIER_USE_CSP'))  define('HTML_MODIFIER_USE_CSP', true); 




// error settings
if (!defined('ERROR_LOG_ENABLED'))  define('ERROR_LOG_ENABLED', true);

if (!defined('ERROR_LOG_DIRECTORY'))  define('ERROR_LOG_DIRECTORY', 'errors/');

if (!defined('ERROR_LOG_FILE'))  define('ERROR_LOG_FILE', 'error_log.txt');
