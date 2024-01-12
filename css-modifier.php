<?php

class CssModifier
{
    public static $modify = true;

    public static function convertRelativeToAbsolute($baseUrl, $relativeUrl)
    {
        if (strpos($relativeUrl, '//') === 0) {
            // Prepend "http:" to the URL with double slashes
            $relativeUrl = 'http:' . $relativeUrl;
        } elseif (strpos($relativeUrl, 'data:') === 0) {
            return $relativeUrl;
        } else {
            $parsedBaseUrl = parse_url($baseUrl);
            $absolutePath = '';
            if (!strpos($relativeUrl, '/') === 0) {
                $relativeUrl = '/' . $relativeUrl;
                $absolutePath = $parsedBaseUrl['path'] ?? "";
            }
            $absolutePath .=  $relativeUrl;
            $absolutePath = str_replace('//', '/', $absolutePath);

            $relativeUrl = "{$parsedBaseUrl['scheme']}://{$parsedBaseUrl['host']}{$absolutePath}";
        }
        return $relativeUrl;
    }

    public static function modifyUrls($cssContent, $baseProxyUrl)
    {
        if (!self::$modify || !CSS_MODIFIER_ENABLED) {
            return $cssContent;
        }

        // Regular expression pattern to match URLs within url() functions
        $pattern = '/url\([\'"]?(.*?)[\'"]?\)/i';

        // Replace URLs with modified URLs
        $modifiedCssContent = preg_replace_callback($pattern, function ($matches) use ($baseProxyUrl) {
            $url = $matches[1];
            $modifiedUrl = self::convertRelativeToAbsolute($baseProxyUrl, $url);
            $modifiedUrl = self::getBaseProxyUrl() . "?" . PROXY_URL_QUERY_KEY . "=" . urlencode($modifiedUrl);
            return "url('$modifiedUrl')";
        }, $cssContent);

        return $modifiedCssContent;
    }

    private static function getBaseProxyUrl()
    {
        $baseProxyUrl = strtok(PROXY_CURRENT_URL, '?');
        return $baseProxyUrl;
    }
    public static function setModify($modify){
        self::$modify = $modify;
    }
}
