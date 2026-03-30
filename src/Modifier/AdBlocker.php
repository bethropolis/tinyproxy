<?php

declare(strict_types=1);

namespace TinyProxy\Modifier;

use TinyProxy\Config\Configuration;

/**
 * Ad blocker for removing ads from HTML content
 */
class AdBlocker
{
    private bool $enabled;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->enabled = $config->getBool('modifiers.adblock.enabled', false);
    }

    /**
     * Block ads in HTML content
     */
    public function blockAds(string $html): string
    {
        if (!$this->enabled || empty($html)) {
            return $html;
        }

        // Remove script tags with known ad libraries
        $html = preg_replace(
            '/<script[^>]*src=["\'][^"\']*(?:adsense|doubleclick|adroll|googlesyndication|googleadservices)[^"\']*["\'][^>]*>.*?<\/script>/is',
            '',
            $html
        );

        // Remove iframe tags with ad URLs
        $html = preg_replace(
            '/<iframe[^>]*src=["\'][^"\']*(?:ads?|advert|advertisement)[^"\']*["\'][^>]*>.*?<\/iframe>/is',
            '',
            $html
        );

        // Remove divs with ad-related class names
        $html = preg_replace(
            '/<div[^>]*class=["\'][^"\']*(?:ad|advert|advertisement|sponsor|commercial|banner)[^"\']*["\'][^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove images with ad-related file names
        $html = preg_replace(
            '/<img[^>]*src=["\'][^"\']*(?:ad|banner|sponsor|advertisement)[^"\']*["\'][^>]*>/is',
            '',
            $html
        );

        // Remove 1x1 tracking pixels
        $html = preg_replace(
            '/<img[^>]*width=["\']1["\'][^>]*height=["\']1["\'][^>]*>/is',
            '',
            $html
        );

        // Remove <ins> tags (often used for ads)
        $html = preg_replace('/<ins[^>]*>.*?<\/ins>/is', '', $html);

        return $html;
    }
}
