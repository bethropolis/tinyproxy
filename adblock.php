<?php
class AdBlocker {
    private $html;

    public function __construct($html) {
        $this->html = $html;
    }

    public function blockAds() {
        $this->removeScriptTagsWithAdLibraries();
        $this->removeIframeTagsWithAdUrls();
        $this->removeDivsWithAdClassNames();
        $this->removeImagesWithAdFileNames();
        $this->removeTrackingPixels();
        $this->removeKnownAdNetworksHtmlPatterns();
        return $this->html;
    }

    private function removeScriptTagsWithAdLibraries() {
        $this->html = preg_replace('/<script[^>]*src="[^"]*(adsense|doubleclick|adroll)[^"]*"[^>]*>.*?<\/script>/is', '', $this->html);
    }

    private function removeIframeTagsWithAdUrls() {
        $this->html = preg_replace('/<iframe[^>]*src="[^"]*(ads|ad|advert)[^"]*"[^>]*>.*?<\/iframe>/is', '', $this->html);
    }

    private function removeDivsWithAdClassNames() {
        $this->html = preg_replace('/<div[^>]*class="[^"]*(ad|advert|sponsor|commercial)[^"]*"[^>]*>.*?<\/div>/is', '', $this->html);
    }

    private function removeImagesWithAdFileNames() {
        $this->html = preg_replace('/<img[^>]*src="[^"]*(ad|banner|sponsor)[^"]*"[^>]*>/is', '', $this->html);
    }

    private function removeTrackingPixels() {
        $this->html = preg_replace('/<img[^>]*width="1"[^>]*height="1"[^>]*>/is', '', $this->html);
    }

    private function removeKnownAdNetworksHtmlPatterns() {
        $this->html = preg_replace('/<ins[^>]*>.*?<\/ins>/is', '', $this->html);
    }
}