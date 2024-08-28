<?php
require 'vendor/autoload.php'; // Load Guzzle library
require 'cache.php'; // Include cache functions
require 'error.php';
require "css-modifier.php"; // Include the CssModifier class
require 'html-modifier.php'; // Include the HtmlModifier class

if (DEBUG_ENABLED) require 'debug.php';


class ProxyService
{
    private $currentHost;
    private $allowedOrigins;
    private $cache;
    private $logger;
    private $cacheImages;
    private $textTypes;
    private $modify;

    public function __construct()
    {
        $this->textTypes = TEXT_TYPES;
        $this->cacheImages = CACHE_IMAGES;
        $this->allowedOrigins = PROXY_ALLOWED_ORIGINS;
        $this->currentHost = PROXY_HOST;
        $this->cache = new Cache();
        $this->logger = new Logger();
        $this->modify = isset($_GET[MODIFY_CONTENT]) ? filter_var($_GET[MODIFY_CONTENT], FILTER_VALIDATE_BOOLEAN) : true;
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
        if (in_array("*", $this->allowedOrigins)) {
            return true;
        }

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            return in_array($origin, $this->allowedOrigins);
        }

        // Allow local requests
        return true;
    }

    private function cspHeader()
    {
        if (PROXY_USE_CSP) {
            header("Content-Security-Policy: script-src 'self' {$this->currentHost};");
        }
    }

    private function handleProxyRequest($targetUrl, $useCache)
    {
        $cacheKey = md5($targetUrl);
        $cachedContentExists = $this->cache->has($cacheKey);

        $this->cspHeader();

        if ($cachedContentExists && $useCache) {
            $this->serveCachedContent($cacheKey);
        } else {
            $this->fetchAndProcessContent($targetUrl, $cacheKey);
        }
    }

    private function serveCachedContent($cacheKey)
    {
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData === false) {
            // Handle cache miss or error
            http_response_code(404);
            echo "Cache miss or error.";
            return;
        }
    
        $cachedData = json_decode($cachedData, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($cachedData['content'], $cachedData['content_type'])) {
            // Handle JSON decode error or missing keys
            http_response_code(500);
            echo "Invalid cache data.";
            return;
        }
    
        $cachedContent = $cachedData['content'];
        $cachedContentType = $cachedData['content_type'];
        header("Content-Type: {$cachedContentType}");
    
        if (strpos($cachedContentType, 'image/') === 0) {
            $cachedContent = base64_decode($cachedContent);
            if ($cachedContent === false) {
                // Handle base64 decode error
                http_response_code(500);
                echo "Invalid image data.";
                return;
            }
        }
    
        echo $cachedContent;
    }

    private function fetchAndProcessContent($targetUrl, $cacheKey)
    {
        $client = new GuzzleHttp\Client();
        try {
            $request = $client->get($targetUrl, [
                'headers' => [
                    'Referer' => $targetUrl ,
                    'User-Agent' => PROXY_USER_AGENT
                ]
            ]);

            $contentType = $request->getHeaderLine('Content-Type');
            header("Content-Type: {$contentType}");
            $content = $request->getBody();


            $contentTypeParts = explode(';', $contentType);
            $cleanedContentType = trim($contentTypeParts[0]);

            if (in_array($cleanedContentType, $this->textTypes)) {
                if (strpos($cleanedContentType, 'text/html') !== false) {
                    $content = $this->processHtmlContent($content, $targetUrl, $cacheKey);
                } elseif (strpos($cleanedContentType, 'text/css') !== false) {
                    $content = $this->processCssContent($content, $targetUrl, $cacheKey);
                }else{
                    $this->storeTextContent($content, $cacheKey, $cleanedContentType);
                }

                
            } else {
                if (strpos($cleanedContentType, 'image/') === 0 && $this->cacheImages) {
                    $this->cacheImageContent($request, $content, $cacheKey);
                }

                header("Cache-Control: max-age=" . CACHE_MAX_AGE_HEADER);
            }

            echo $content;
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $this->logError("Request failed: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo "An error occurred";
        }
    }

    private function processHtmlContent($content, $targetUrl, $cacheKey)
    {
        HtmlModifier::setModify($this->modify);
        $baseProxyUrl = $targetUrl;
        $baseProxyUrl = strtok($baseProxyUrl, '?');
        $htmlContent = $content->getContents();
        $modifiedHtml = HtmlModifier::modifyRelativeUrls($htmlContent, $baseProxyUrl);
        $modifiedHtml = HtmlModifier::addTopBar($modifiedHtml);

        $this->cache->set($cacheKey, [
            'content' => $modifiedHtml,
            'content_type' => 'text/html'
        ]);
        return $modifiedHtml;
    }

    private function processCssContent($content, $targetUrl, $cacheKey)
    {
        CssModifier::setModify($this->modify);
        $cssContent = $content->getContents();
        $modifiedCssContent = CssModifier::modifyUrls($cssContent, $targetUrl);
        $this->cache->set($cacheKey, [
            'content' => $modifiedCssContent,
            'content_type' => 'text/css'
        ]);
        return $modifiedCssContent;
    }


    private function storeTextContent($content, $cacheKey, $contentType)
    {
        $this->cache->set($cacheKey, [
            'content' => $content,
            'content_type' => $contentType
        ]);
    }

    private function cacheImageContent($request, $content, $cacheKey)
    {
        $contentLength = $request->getHeaderLine('Content-Length');
        $contentLengthBytes = intval($contentLength);

        if ($contentLengthBytes <= CACHE_MAX_SIZE) {
            $base64ImageData = base64_encode($content);
            $this->cache->set($cacheKey, [
                'content' => $base64ImageData,
                'content_type' => $request->getHeaderLine('Content-Type')
            ]);
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
