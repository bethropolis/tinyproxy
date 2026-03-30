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
<div style="background-color: #f0f0f0 !important; padding: 10px !important; text-align: center !important; z-index: 9999 !important; position: sticky !important; top: 0 !important; display: flex !important; gap: 10px !important; align-items: center !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;">
    <form action="/" method="get" style="flex: 1 !important; display: flex !important; gap: 10px !important; margin: 0 !important;">
        <input type="url" name="{$queryKey}" value="{$currentUrl}" placeholder="Enter URL..." style="flex: 1 !important; padding: 8px !important; border: 1px solid #ccc !important; border-radius: 4px !important;" required>
        <button type="submit" style="padding: 8px 20px !important; background-color: #0070f3 !important; color: white !important; border: none !important; border-radius: 4px !important; cursor: pointer !important; font-weight: bold !important;">Go</button>
    </form>
</div>
HTML;

        return $topBar . $htmlContent;
    }
}
