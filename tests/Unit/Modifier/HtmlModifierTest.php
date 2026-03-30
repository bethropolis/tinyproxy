<?php

declare(strict_types=1);

namespace TinyProxy\Tests\Unit\Modifier;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use TinyProxy\Config\Configuration;
use TinyProxy\Modifier\HtmlModifier;
use TinyProxy\Modifier\CssModifier;
use TinyProxy\Modifier\AdBlocker;

#[CoversClass(HtmlModifier::class)]
class HtmlModifierTest extends TestCase
{
    private Configuration|MockObject $configMock;
    private CssModifier|MockObject $cssModifierMock;
    private AdBlocker|MockObject $adBlockerMock;
    private HtmlModifier $modifier;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configMock = $this->createMock(Configuration::class);
        $this->cssModifierMock = $this->createMock(CssModifier::class);
        $this->adBlockerMock = $this->createMock(AdBlocker::class);
        
        $this->configMock->method('getBool')->willReturnMap([
            ['modifiers.html.enabled', true, true],
            ['modifiers.html.show_top_bar', true, false], // Disable top bar by default for easier assertions
        ]);
        
        $this->configMock->method('getArray')->willReturnMap([
            ['modifiers.html.url_attributes', ['href', 'src', 'action'], ['href', 'src', 'action']],
        ]);
        
        $this->configMock->method('getString')->willReturnMap([
            ['PROXY_URL_QUERY_KEY', 'url', 'url'],
            ['APP_URL', 'http://localhost:8080', 'http://localhost:8080'],
        ]);
        
        $this->adBlockerMock->method('blockAds')->willReturnArgument(0);
        $this->cssModifierMock->method('modify')->willReturnArgument(0);
        
        $this->modifier = new HtmlModifier(
            $this->configMock,
            $this->cssModifierMock,
            $this->adBlockerMock
        );
    }

    public function testSupportsHtml(): void
    {
        $this->assertTrue($this->modifier->supports('text/html'));
        $this->assertTrue($this->modifier->supports('text/html; charset=utf-8'));
        $this->assertFalse($this->modifier->supports('text/css'));
    }

    public function testReturnsOriginalIfDisabled(): void
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getBool')->willReturnMap([
            ['modifiers.html.enabled', true, false],
            ['modifiers.html.show_top_bar', true, false],
        ]);
        $configMock->method('getArray')->willReturnMap([
            ['modifiers.html.url_attributes', ['href', 'src', 'action'], ['href', 'src', 'action']],
        ]);
        
        $modifier = new HtmlModifier($configMock, $this->cssModifierMock, $this->adBlockerMock);
        
        $html = '<a href="/test">Link</a>';
        $this->assertEquals($html, $modifier->modify($html, 'http://example.com'));
    }

    public function testModifiesHrefAttributes(): void
    {
        $html = '<html><body><a href="/about">About</a></body></html>';
        $modified = $this->modifier->modify($html, 'http://example.com');
        
        $expectedUrl = 'http://localhost:8080?url=' . urlencode('http://example.com/about');
        $this->assertStringContainsString($expectedUrl, $modified);
    }

    public function testModifiesSrcAttributes(): void
    {
        $html = '<html><body><img src="image.png"></body></html>';
        $modified = $this->modifier->modify($html, 'http://example.com/path/index.html');
        
        $expectedUrl = 'http://localhost:8080?url=' . urlencode('http://example.com/path/image.png');
        $this->assertStringContainsString($expectedUrl, $modified);
    }

    public function testModifiesSrcsetAttributes(): void
    {
        $html = '<html><body><img srcset="img1.png 1x, /img2.png 2x"></body></html>';
        $modified = $this->modifier->modify($html, 'http://example.com');
        
        $expectedUrl1 = 'http://localhost:8080?url=' . urlencode('http://example.com/img1.png');
        $expectedUrl2 = 'http://localhost:8080?url=' . urlencode('http://example.com/img2.png');
        
        $this->assertStringContainsString($expectedUrl1, $modified);
        $this->assertStringContainsString($expectedUrl2, $modified);
    }

    public function testAddsTopBarWhenEnabled(): void
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getBool')->willReturnMap([
            ['modifiers.html.enabled', true, true],
            ['modifiers.html.show_top_bar', true, true],
        ]);
        $configMock->method('getArray')->willReturnMap([
            ['modifiers.html.url_attributes', ['href', 'src', 'action'], ['href', 'src', 'action']],
        ]);
        $configMock->method('getString')->willReturnMap([
            ['PROXY_URL_QUERY_KEY', 'url', 'url'],
            ['APP_URL', 'http://localhost:8080', 'http://localhost:8080'],
        ]);
        
        $modifier = new HtmlModifier($configMock, $this->cssModifierMock, $this->adBlockerMock);
        
        $html = '<html><body>Hello</body></html>';
        $modified = $modifier->modify($html, 'http://example.com');
        
        $this->assertStringContainsString('<form action="/" method="get"', $modified);
        $this->assertStringContainsString('value="http://example.com"', $modified);
    }
}
