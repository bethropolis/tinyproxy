<?php

require_once 'adblock.php';


class HtmlModifier
{
    public static $modify = true;

    public static function modifyRelativeUrls($html, $baseProxyUrl)
    {
        if (!self::$modify || !HTML_MODIFIER_ENABLED) {
            return $html;
        }
        if (empty($html)) {
            return;
        }


        $adBlocker = new AdBlocker($html);
        $htmlContent = $adBlocker->blockAds();
        
        DebugLogger::debug("adblock.log.txt", "htmlContent: ", $htmlContent);

        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent); // Suppress warnings

        // Check for <base> element in the <head>
        $baseElement = $dom->getElementsByTagName('base')->item(0);
        $baseUrl = $baseElement ? $baseElement->getAttribute('href') : $baseProxyUrl;

        $elements = $dom->getElementsByTagName('*');
        foreach ($elements as $element) {
            foreach (HTML_MODIFIER_URL_ATTRIBUTES as $attribute) {
                $url = $element->getAttribute($attribute);
                if (!empty($url)) {
                    $absoluteUrl = self::convertRelativeToAbsolute($baseUrl, $url);
                    $newUrl = self::getBaseProxyUrl() . "?" . PROXY_URL_QUERY_KEY . "=" . urlencode($absoluteUrl);
                    $element->setAttribute($attribute, $newUrl);
                }
            }
            // Handle srcset attribute for img tags
            if ($element->nodeName === 'img') {
                $srcset = $element->getAttribute('srcset');
                if (!empty($srcset)) {
                    $modifiedSrcset = self::modifySrcsetUrls($baseUrl, $srcset);
                    $element->setAttribute('srcset', $modifiedSrcset);
                }
            }
        }

        // Find all <style> tags
        $styleTags = $dom->getElementsByTagName('style');
        foreach ($styleTags as $styleTag) {
            $cssContent = $styleTag->nodeValue;
            $modifiedCss = CssModifier::modifyUrls($cssContent, $baseUrl);
            $styleTag->nodeValue = $modifiedCss;
        }

        return $dom->saveHTML();
    }

    public static function addTopBar($htmlContent)
    {
        if (!self::$modify) {
            return $htmlContent;
        }
        $topBar = '
        <div style="background-color: #f0f0f0 !important; padding: 5px !important; text-align: center !important; z-index: 1000 !important; height: fit-content !important; width: 100% !important; position: sticky !important; top: 0 !important; display: flex;">
            <form action="' . $_SERVER['PHP_SELF'] . '" method="get" style="margin: 5px 0 !important; width: 90%; display: flex; gap: 10px">
                <input type="url" name="url" value="' . urldecode($_GET[PROXY_URL_QUERY_KEY]) . '" placeholder="enter url..." style="margin: 0 !important; width: 70% !important; border: 1px solid #ccc !important; padding: 5px !important;" required>
                <button type="submit" style="padding: 5px 20px !important; background-color: #0070f3 !important; color: white !important; border: none !important; cursor: pointer !important;">Go</button>
            </form>
            <a href="./public/offline.php" ><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 256 256"><path fill="currentColor" d="M128 26a102 102 0 1 0 102 102A102.12 102.12 0 0 0 128 26m81.57 64h-40.38a132.6 132.6 0 0 0-25.73-50.67A90.29 90.29 0 0 1 209.57 90m8.43 38a89.7 89.7 0 0 1-3.83 26h-42.36a155.4 155.4 0 0 0 0-52h42.36a89.7 89.7 0 0 1 3.83 26m-90 87.83a110 110 0 0 1-15.19-19.45A124.2 124.2 0 0 1 99.35 166h57.3a124.2 124.2 0 0 1-13.46 30.38A110 110 0 0 1 128 215.83M96.45 154a139.2 139.2 0 0 1 0-52h63.1a139.2 139.2 0 0 1 0 52ZM38 128a89.7 89.7 0 0 1 3.83-26h42.36a155.4 155.4 0 0 0 0 52H41.83A89.7 89.7 0 0 1 38 128m90-87.83a110 110 0 0 1 15.19 19.45A124.2 124.2 0 0 1 156.65 90h-57.3a124.2 124.2 0 0 1 13.46-30.38A110 110 0 0 1 128 40.17m-15.46-.84A132.6 132.6 0 0 0 86.81 90H46.43a90.29 90.29 0 0 1 66.11-50.67M46.43 166h40.38a132.6 132.6 0 0 0 25.73 50.67A90.29 90.29 0 0 1 46.43 166m97 50.67A132.6 132.6 0 0 0 169.19 166h40.38a90.29 90.29 0 0 1-66.11 50.67Z"/></svg></a>
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

        // DebugLogger::dump($baseUrl, $relativeUrl);

        // If the relative URL is already absolute, return it as is
        if (isset($parsedRelativeUrl['scheme'])) {
            return $relativeUrl;
        }

        // Initialize the absolute path
        $absolutePath = '';

        // Handle relative path indicators
        if (isset($parsedRelativeUrl['path'])) {
            $relativePath = $parsedRelativeUrl['path'];

            if (strpos($relativePath, '/') === 0) {
                // Root-relative path
                $absolutePath = $relativePath;
            } else {
                // Handle relative paths like './' and '../'
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

        // Construct the absolute URL
        $absoluteUrl = "{$parsedBaseUrl['scheme']}://{$parsedBaseUrl['host']}{$absolutePath}";
        return $absoluteUrl;
    }

    private static function getBaseProxyUrl()
    {
        $baseProxyUrl = strtok(PROXY_CURRENT_URL, '?');
        return $baseProxyUrl;
    }

    public static function setModify($modify)
    {
        self::$modify = $modify;
    }
}
