<?php
require 'vendor/autoload.php'; // Load Guzzle library
require 'cache.php'; // Include cache functions
require "error.php";

class ProxyService
{
    private $allowedOrigins = ['http://localhost']; // Add your allowed origins
    private $cache;
    private $logger;

    public function __construct()
    {
        $this->cache = new Cache();
        $this->logger = new Logger();
    }

    public function proxyRequest($targetUrl)
    {
        header('Content-type: text/plain');
        // Validate input URL
        if (!$this->isValidUrl($targetUrl)) {
            $this->logError("Invalid URL: $targetUrl");
            http_response_code(400); // Bad Request
            echo "Invalid URL";
            return;
        }

        // Check allowed origin
        if (!$this->isValidOrigin()) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $this->logError("Forbidden origin: {$origin}");
            http_response_code(403); // Forbidden
            echo "Forbidden";
            return;
        }

        // Fetch from cache or make request
        $cacheKey = md5($targetUrl);
        $cachedContentExists = $this->cache->has($cacheKey);

        if ($cachedContentExists) {
            echo $this->cache->get($cacheKey);
        } else {
            $client = new GuzzleHttp\Client();
            try {
                $response = $client->get($targetUrl);
                $content = $response->getBody();

                // Cache the response
                $this->cache->set($cacheKey, $content);

                echo $content;
            } catch (Exception $e) {
                $this->logError("Request failed: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                echo "An error occurred";
            }
        }
    }

    private function isValidUrl($url)
    {
        // Add your URL validation logic here
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isValidOrigin()
    {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            return in_array($origin, $this->allowedOrigins);
        }

        // Allow local requests
        return true;
    }



    private function logError($message)
    {
        $this->logger->log($message);
    }
}
