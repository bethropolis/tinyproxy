<?php

declare(strict_types=1);

namespace TinyProxy\Modifier;

use TinyProxy\Config\Configuration;
use TinyProxy\Util\UrlHelper;

/**
 * CSS content modifier
 */
class CssModifier implements ModifierInterface
{
    private bool $enabled;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->enabled = $config->getBool('modifiers.css.enabled', true);
    }

    public function supports(string $contentType): bool
    {
        return str_starts_with($contentType, 'text/css');
    }

    public function modify(string $content, string $baseUrl): string
    {
        if (!$this->enabled || empty($content)) {
            return $content;
        }

        // Replace URLs in url() functions
        $pattern = '/url\([\'"]?([^\)\'\"]+)[\'"]?\)/i';
        
        return preg_replace_callback($pattern, function ($matches) use ($baseUrl) {
            $url = trim($matches[1]);
            
            // Skip data URIs
            if (str_starts_with($url, 'data:')) {
                return $matches[0];
            }

            // Handle protocol-relative URLs
            if (str_starts_with($url, '//')) {
                $url = 'https:' . $url;
            }

            // Make absolute
            $absoluteUrl = UrlHelper::makeAbsolute($url, $baseUrl);
            
            // Make proxied
            $proxiedUrl = $this->makeProxiedUrl($absoluteUrl);
            
            return "url('{$proxiedUrl}')";
        }, $content);
    }

    /**
     * Create proxied URL
     */
    private function makeProxiedUrl(string $url): string
    {
        $queryKey = $this->config->getString('PROXY_URL_QUERY_KEY', 'url');
        $baseUrl = $this->config->getString('APP_URL', 'http://localhost:8080');
        
        return $baseUrl . '?' . $queryKey . '=' . urlencode($url);
    }
}
