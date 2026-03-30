<?php

declare(strict_types=1);

namespace TinyProxy\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use TinyProxy\Config\Configuration;
use TinyProxy\Cache\FileCache;
use TinyProxy\Cache\CachedContent;
use TinyProxy\Util\FileHelper;

#[CoversClass(FileCache::class)]
class FileCacheTest extends TestCase
{
    private Configuration|MockObject $configMock;
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheDir = __DIR__ . '/../../../var/cache/test_cache';
        
        $this->configMock = $this->createMock(Configuration::class);
        $this->configMock->method('getString')->willReturnMap([
            ['cache.directory', 'var/cache', $this->cacheDir],
        ]);
        $this->configMock->method('getInt')->willReturnMap([
            ['cache.default_ttl', 3600, 3600],
        ]);
        $this->configMock->method('getBool')->willReturnMap([
            ['cache.enabled', true, true],
            ['cache.compression', true, false],
        ]);
        $this->configMock->method('getArray')->willReturnMap([
            ['cache.cachable_types', [
                'text/javascript',
                'text/css',
                'text/html',
                'application/json',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/svg+xml'
            ], ['text/html', 'application/json']],
        ]);
        
        $this->cache = new FileCache($this->configMock);
    }

    protected function tearDown(): void
    {
        // Recursively remove test cache directory
        if (is_dir($this->cacheDir)) {
            $this->removeDirectory($this->cacheDir);
        }
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            (is_dir($path)) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testSetAndGetCache(): void
    {
        $key = 'test_key_1';
        $contentString = '<html><body>Test Content</body></html>';
        $cachedContent = new CachedContent($contentString, 'text/html', strlen($contentString));

        $this->cache->set($key, $cachedContent);

        $this->assertTrue($this->cache->has($key));
        
        $retrieved = $this->cache->get($key);
        $this->assertNotNull($retrieved);
        $this->assertEquals($contentString, $retrieved->getContent());
        $this->assertEquals('text/html', $retrieved->getContentType());
    }

    public function testCacheDoesNotStoreUncachableTypes(): void
    {
        $key = 'test_key_2';
        $contentString = 'alert("test");';
        // 'text/javascript' is not in our mocked allowed types for this test
        $cachedContent = new CachedContent($contentString, 'text/javascript', strlen($contentString));

        $this->cache->set($key, $cachedContent);

        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    public function testDeleteCache(): void
    {
        $key = 'test_key_3';
        $cachedContent = new CachedContent('content', 'text/html', 7);

        $this->cache->set($key, $cachedContent);
        $this->assertTrue($this->cache->has($key));

        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }

    public function testClearCache(): void
    {
        $content1 = new CachedContent('content1', 'text/html', 8);
        $content2 = new CachedContent('content2', 'text/html', 8);

        $this->cache->set('key1', $content1);
        $this->cache->set('key2', $content2);

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        $this->cache->clear();

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }
}
