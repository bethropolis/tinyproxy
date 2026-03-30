<?php

declare(strict_types=1);

namespace TinyProxy\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use TinyProxy\Config\Configuration;
use TinyProxy\Security\RateLimiter;

#[CoversClass(RateLimiter::class)]
class RateLimiterTest extends TestCase
{
    private Configuration|MockObject $configMock;
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheDir = __DIR__ . '/../../../var/cache/rate_limit';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        $this->configMock = $this->createMock(Configuration::class);
        $this->configMock->method('getBool')->willReturnMap([
            ['security.rate_limit_enabled', true, true],
        ]);
        $this->configMock->method('getInt')->willReturnMap([
            ['security.rate_limit_per_minute', 60, 5],
            ['security.rate_limit_per_hour', 1000, 100],
        ]);
        $this->configMock->method('getString')->willReturnMap([
            ['security.rate_limit_storage', 'apcu', 'file'], // force file storage for CLI tests
        ]);
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function testCheckAllowsRequestsWithinLimit(): void
    {
        $limiter = new RateLimiter($this->configMock);
        
        $this->assertTrue($limiter->check('user1'));
        $this->assertTrue($limiter->check('user1'));
        $this->assertTrue($limiter->check('user1'));
        $this->assertTrue($limiter->check('user1'));
        $this->assertTrue($limiter->check('user1'));
    }

    public function testCheckBlocksRequestsExceedingLimit(): void
    {
        $limiter = new RateLimiter($this->configMock);
        
        $this->assertTrue($limiter->check('user2'));
        $this->assertTrue($limiter->check('user2'));
        $this->assertTrue($limiter->check('user2'));
        $this->assertTrue($limiter->check('user2'));
        $this->assertTrue($limiter->check('user2'));
        
        // 6th request should be blocked
        $this->assertFalse($limiter->check('user2'));
    }

    public function testGetRemainingRequests(): void
    {
        $limiter = new RateLimiter($this->configMock);
        
        $this->assertEquals(5, $limiter->getRemainingRequests('user3', 60));
        
        $limiter->check('user3');
        
        $this->assertEquals(4, $limiter->getRemainingRequests('user3', 60));
    }

    public function testDisabledRateLimiterAlwaysAllows(): void
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getBool')->willReturnMap([
            ['security.rate_limit_enabled', true, false],
        ]);
        
        $limiter = new RateLimiter($configMock);
        
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->check('user4'));
        }
    }
}
