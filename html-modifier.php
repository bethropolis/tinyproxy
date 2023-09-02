<?php

class HtmlModifier
{
    public static function modifyRelativeUrls($htmlContent, $baseProxyUrl)
    {
        if (!HTML_MODIFIER_ENABLED) {
            return $htmlContent;
        }

        if (empty($htmlContent)) {
            return;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent); // Suppress warnings

        $elements = $dom->getElementsByTagName('*');
        foreach ($elements as $element) {
            foreach (HTML_MODIFIER_URL_ATTRIBUTES as $attribute) {
                $url = $element->getAttribute($attribute);
                if (!empty($url)) {
                    $absoluteUrl = self::convertRelativeToAbsolute($baseProxyUrl, $url);
                    $newUrl = self::getBaseProxyUrl() . "?" . PROXY_URL_QUERY_KEY . "=" . urlencode($absoluteUrl);
                    $element->setAttribute($attribute, $newUrl);
                }
            }

            // Handle srcset attribute for img tags
            if ($element->nodeName === 'img') {
                $srcset = $element->getAttribute('srcset');
                if (!empty($srcset)) {
                    $modifiedSrcset = self::modifySrcsetUrls($baseProxyUrl, $srcset);
                    $element->setAttribute('srcset', $modifiedSrcset);
                }
            }
        }

        // Find all <style> tags
        $styleTags = $dom->getElementsByTagName('style');
        foreach ($styleTags as $styleTag) {
            $cssContent = $styleTag->nodeValue;
            $modifiedCss = CssModifier::modifyUrls($cssContent, $baseProxyUrl);
            $styleTag->nodeValue = $modifiedCss;
        }




        return $dom->saveHTML();
    }


    public static function addTopBar($htmlContent)
    {
        $topBar = '
            <div style="background-color: #f0f0f0; padding: 5px; text-align: center; z-index: 1000; height: fit-content !important; width: 100%; position: sticky; top: 0;">
                <form action="' . $_SERVER['PHP_SELF']. '" method="get" style="margin: 5px 0 !important; ">
                    <input type="url" name="url" value="'. urldecode($_GET[PROXY_URL_QUERY_KEY]) .'" placeholder="enter url..." style="margin: 0 !important; width: 70%;border: 1px solid #ccc; padding: 5px;" required>
                    <button type="submit" style="padding: 5px 20px; background-color: #0070f3; color: white; border: none; cursor: pointer;">Go</button>
                </form>
            </div>
        ';

        return $topBar . $htmlContent;
    }

    private static function modifySrcsetUrls($baseProxyUrl, $srcset)
    {
        $urls = explode(',', $srcset);
        $modifiedUrls = [];

        foreach ($urls as $url) {
            $urlParts = explode(' ', trim($url));
            if (count($urlParts) >= 2) {
                $imageUrl = trim($urlParts[0]);
                $imageSize = trim($urlParts[1]);
                $absoluteImageUrl = self::convertRelativeToAbsolute($baseProxyUrl, $imageUrl);
                $newImageUrl = self::getBaseProxyUrl() . "?" . PROXY_URL_QUERY_KEY . "=" . urlencode($absoluteImageUrl);
                $modifiedUrls[] = "{$newImageUrl} {$imageSize}";
            }
        }

        return implode(', ', $modifiedUrls);
    }

    private static function convertRelativeToAbsolute($baseUrl, $relativeUrl)
    {
        $parsedBaseUrl = parse_url($baseUrl);
        $parsedRelativeUrl = parse_url($relativeUrl);

        if (isset($parsedRelativeUrl['host'])) {
            return $relativeUrl; // It's already an absolute URL
        }

        $absolutePath = '';

        // Handle relative path indicators like './'
        if (isset($parsedRelativeUrl['path'])) {
            $relativePath = $parsedRelativeUrl['path'];

            if (strpos($relativePath, '/') === 0) {
                // Relative path starts with '/', consider it from the root
                $absolutePath = $relativePath;
            } else {


                $basePathParts = explode('/', $parsedBaseUrl['path'] ?? "");
                $relativePathParts = explode('/', $relativePath);

                // Remove last element from basePathParts as it's the current page's filename
                array_pop($basePathParts);

                foreach ($relativePathParts as $part) {
                    if ($part === '..') {
                        array_pop($basePathParts);
                    } elseif ($part !== '.') {
                        $basePathParts[] = $part;
                    }
                }

                $absolutePath = '/' . implode('/', $basePathParts);
            }
        }

        // Append query and fragment components if present in the relative URL
        if (isset($parsedRelativeUrl['query'])) {
            $absolutePath .= '?' . $parsedRelativeUrl['query'];
        }
        if (isset($parsedRelativeUrl['fragment'])) {
            $absolutePath .= '#' . $parsedRelativeUrl['fragment'];
        }

        $absoluteUrl = "{$parsedBaseUrl['scheme']}://{$parsedBaseUrl['host']}{$absolutePath}";

        return $absoluteUrl;
    }




    private static function getBaseProxyUrl()
    {
        $baseProxyUrl = strtok(PROXY_CURRENT_URL, '?');
        return $baseProxyUrl;
    }
}
