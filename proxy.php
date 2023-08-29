<?php
require 'vendor/autoload.php'; // Load Guzzle library
require 'cache.php'; // Include cache functions
require 'error.php';
require "css-modifier.php"; // Include the CssModifier class
require 'html-modifier.php'; // Include the HtmlModifier class

class ProxyService
{
    private $allowedOrigins = ['*']; // Add your allowed origins
    private $cache;
    private $logger;
    private  $cachableTypes = [
        'text/javascript',
        'text/css',
        'text/html',
        'application/json',
        'text/plain'
    ];

    public function __construct()
    {
        $this->cache = new Cache();
        $this->logger = new Logger();
        // $this->cache->clearCache();
    }

    public function proxyRequest($targetUrl)
    {
        $useCache = !isset($_GET['cache']) || $_GET['cache'] === 'true';

        $targetUrl = $this->fixUrl($targetUrl);

        if (!$this->validateProxyRequest($targetUrl)) {
            http_response_code(400); // Bad Request
            echo "Invalid URL";
            return;
        }

        if (!$this->isAllowedOrigin()) {
            http_response_code(403); // Forbidden
            echo "Forbidden";
            return;
        }

        $this->handleProxyRequest($targetUrl, $useCache);
    }

    private function fixUrl($url)
    {
        if (strpos($url, '//') === 0) {
            $url = "https" . ':' . $url;
        }

        return $url;
    }

    private function validateProxyRequest($targetUrl)
    {
        return $this->isValidUrl($targetUrl);
    }


    private function isAllowedOrigin()
    {
        if(in_array("*", $this->allowedOrigins)){
            return true;
        }

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            return in_array($origin, $this->allowedOrigins);
        }

        // Allow local requests
        return true;
    }

    private function handleProxyRequest($targetUrl, $useCache)
    {
        $cacheKey = md5($targetUrl);
        $cachedContentExists = $this->cache->has($cacheKey);

        if ($cachedContentExists && $useCache) {
            $cachedData = $this->cache->get($cacheKey);
            $cachedData = json_decode($cachedData, true);
            $cachedContent = $cachedData['content'];
            $cachedContentType  = $cachedData['content_type'];
            header("Content-Type: {$cachedContentType}");
            echo $cachedContent;
            return;
        } else {
            $client = new GuzzleHttp\Client();
            try {
                $response = $client->get($targetUrl);

                $contentType = $response->getHeaderLine('Content-Type');
                header("Content-Type: {$contentType}");

                $content = $response->getBody();

                // Extract the content type without charset
                $contentTypeParts = explode(';', $contentType);
                $cleanedContentType = trim($contentTypeParts[0]);

                if (in_array($cleanedContentType, $this->cachableTypes)) {
                    if (strpos($cleanedContentType, 'text/html') !== false) {
                        $baseProxyUrl = $targetUrl;
                        $baseProxyUrl = strtok($baseProxyUrl, '?');
                        $htmlContent = $content->getContents();
                        $modifiedHtml = HtmlModifier::modifyRelativeUrls($htmlContent, $baseProxyUrl);
                        $content = $modifiedHtml;
                    } elseif (strpos($cleanedContentType, 'text/css') !== false) {
                        // Modify CSS content if it's a CSS file
                        $cssContent = $content->getContents();
                        $modifiedCssContent = CssModifier::modifyUrls($cssContent, $targetUrl);
                        $content = $modifiedCssContent;
                    }

                    $this->cache->set($cacheKey, [
                        'content' => $content,
                        "content_type" => $contentType
                    ]);
                }else{
                    // put a cache header for 1hr 
                    header("Cache-Control: max-age=3600");
                }

                echo $content;
            } catch (GuzzleHttp\Exception\RequestException $e) {
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

    private function logError($message)
    {
        $this->logger->log($message);
    }
}
