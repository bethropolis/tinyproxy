<?php

declare(strict_types=1);

namespace TinyProxy\Modifier;

use DOMDocument;
use TinyProxy\Config\Configuration;
use TinyProxy\Util\UrlHelper;

/**
 * HTML content modifier
 */
class HtmlModifier implements ModifierInterface
{
    private bool $enabled;
    private array $urlAttributes;
    private bool $showTopBar;

    public function __construct(
        private readonly Configuration $config,
        private readonly CssModifier $cssModifier,
        private readonly AdBlocker $adBlocker
    ) {
        $this->enabled = $config->getBool('modifiers.html.enabled', true);
        $this->urlAttributes = $config->getArray('modifiers.html.url_attributes', [
            'href', 'src', 'action'
        ]);
        $this->showTopBar = $config->getBool('modifiers.html.show_top_bar', true);
    }

    public function supports(string $contentType): bool
    {
        return str_starts_with($contentType, 'text/html');
    }

    public function modify(string $content, string $baseUrl): string
    {
        if (!$this->enabled || empty($content)) {
            return $content;
        }

        // Block ads first
        $content = $this->adBlocker->blockAds($content);

        // Parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Get base URL from <base> tag if present
        $baseElement = $dom->getElementsByTagName('base')->item(0);
        $effectiveBaseUrl = $baseElement ? $baseElement->getAttribute('href') : $baseUrl;

        // Modify URLs in attributes
        $this->modifyUrls($dom, $effectiveBaseUrl);

        // Modify srcset attributes
        $this->modifySrcsets($dom, $effectiveBaseUrl);

        // Process inline styles
        $this->processStyleTags($dom, $effectiveBaseUrl);

        // Add top bar if enabled
        if ($this->showTopBar) {
            $modifiedHtml = $dom->saveHTML();
            $modifiedHtml = $this->addTopBar($modifiedHtml, $baseUrl);
            return $modifiedHtml;
        }

        return $dom->saveHTML() ?: $content;
    }

    /**
     * Modify URLs in HTML attributes
     */
    private function modifyUrls(DOMDocument $dom, string $baseUrl): void
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            foreach ($this->urlAttributes as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $url = $element->getAttribute($attribute);
                    
                    if (!empty($url) && !str_starts_with($url, '#')) {
                        $absoluteUrl = UrlHelper::makeAbsolute($url, $baseUrl);
                        $proxiedUrl = $this->makeProxiedUrl($absoluteUrl);
                        $element->setAttribute($attribute, $proxiedUrl);
                    }
                }
            }
        }
    }

    /**
     * Modify srcset attributes in img tags
     */
    private function modifySrcsets(DOMDocument $dom, string $baseUrl): void
    {
        foreach ($dom->getElementsByTagName('img') as $img) {
            if ($img->hasAttribute('srcset')) {
                $srcset = $img->getAttribute('srcset');
                $modifiedSrcset = $this->modifySrcset($srcset, $baseUrl);
                $img->setAttribute('srcset', $modifiedSrcset);
            }
        }
    }

    /**
     * Modify individual srcset value
     */
    private function modifySrcset(string $srcset, string $baseUrl): string
    {
        $sources = explode(',', $srcset);
        $modifiedSources = [];

        foreach ($sources as $source) {
            $parts = preg_split('/\s+/', trim($source), 2);
            
            if (count($parts) >= 1) {
                $url = $parts[0];
                $descriptor = $parts[1] ?? '';
                
                $absoluteUrl = UrlHelper::makeAbsolute($url, $baseUrl);
                $proxiedUrl = $this->makeProxiedUrl($absoluteUrl);
                
                $modifiedSources[] = trim($proxiedUrl . ' ' . $descriptor);
            }
        }

        return implode(', ', $modifiedSources);
    }

    /**
     * Process inline style tags
     */
    private function processStyleTags(DOMDocument $dom, string $baseUrl): void
    {
        foreach ($dom->getElementsByTagName('style') as $styleTag) {
            $cssContent = $styleTag->nodeValue;
            $modifiedCss = $this->cssModifier->modify($cssContent, $baseUrl);
            $styleTag->nodeValue = $modifiedCss;
        }
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

    /**
     * Add navigation top bar
     */
    private function addTopBar(string $htmlContent, string $currentUrl): string
    {
        $queryKey = $this->config->getString('PROXY_URL_QUERY_KEY', 'url');
        
        $topBar = <<<HTML
<div id="tinyproxy-topbar" style="background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px) !important; -webkit-backdrop-filter: blur(10px) !important; padding: 12px 20px !important; z-index: 2147483647 !important; position: sticky !important; top: 0 !important; border-bottom: 1px solid rgba(0,0,0,0.1) !important; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;">
    <form action="/" method="get" style="display: flex !important; max-width: 1200px !important; margin: 0 auto !important; gap: 12px !important; align-items: center !important;">
        <a href="/" title="TinyProxy Home" style="display: flex !important; align-items: center !important; justify-content: center !important; width: 36px !important; height: 36px !important; background: #3b82f6 !important; color: white !important; border-radius: 8px !important; text-decoration: none !important;">
            <svg style="width: 20px !important; height: 20px !important;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
        </a>
        <div style="flex: 1 !important; position: relative !important;">
            <input type="url" name="{$queryKey}" value="{$currentUrl}" placeholder="Enter URL to proxy..." style="width: 100% !important; padding: 10px 16px !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; font-size: 14px !important; color: #1e293b !important; background: #f8fafc !important; outline: none !important; box-sizing: border-box !important;" required>
        </div>
        <button type="submit" style="padding: 10px 20px !important; background: #0f172a !important; color: white !important; border: none !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 500 !important; cursor: pointer !important; white-space: nowrap !important; transition: background 0.2s !important;">Go &rarr;</button>
    </form>
</div>
HTML;

        // Insert right after <body> if present, otherwise prepend
        if (stripos($htmlContent, '<body') !== false) {
            $htmlContent = preg_replace('/(<body[^>]*>)/i', '$1' . "\n" . $topBar, $htmlContent, 1);
        } else {
            $htmlContent = $topBar . $htmlContent;
        }

        return $htmlContent;
    }
}
