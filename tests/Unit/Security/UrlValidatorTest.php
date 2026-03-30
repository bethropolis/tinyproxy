<?php

declare(strict_types=1);

namespace TinyProxy\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use TinyProxy\Config\Configuration;
use TinyProxy\Exception\SecurityException;
use TinyProxy\Security\UrlValidator;

#[CoversClass(UrlValidator::class)]
class UrlValidatorTest extends TestCase
{
    private Configuration|MockObject $configMock;
    private UrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configMock = $this->createMock(Configuration::class);
        
        // Setup default configuration values for a safe environment
        $this->configMock->method('getBool')->willReturnMap([
            ['security.block_private_ips', true, true],
            ['security.block_local_ips', true, true],
            ['security.block_metadata', true, true],
        ]);
        
        $this->configMock->method('getArray')->willReturnMap([
            ['security.url_whitelist', [], []],
            ['security.url_blacklist', [], []],
        ]);
        
        $this->validator = new UrlValidator($this->configMock);
    }

    public function testIsValidReturnsTrueForValidUrl(): void
    {
        // Example.com is typically a safe, external IP
        $this->assertTrue($this->validator->isValid('https://example.com'));
    }

    public function testValidateThrowsOnInvalidUrlFormat(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL format');
        
        $this->validator->validate('not_a_valid_url://');
    }

    public function testValidateThrowsOnNonHttpScheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Only HTTP and HTTPS schemes are allowed');
        
        $this->validator->validate('ftp://example.com/file.zip');
    }

    public function testValidateBlocksLocalhost(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Access to localhost is prohibited');
        
        $this->validator->validate('http://127.0.0.1/admin');
    }

    public function testValidateBlocksLocalhostDomainName(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Access to localhost is prohibited');
        
        $this->validator->validate('http://localhost:8080');
    }

    public function testValidateBlocksCloudMetadata(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Access to cloud metadata endpoints is prohibited');
        
        $this->validator->validate('http://169.254.169.254/latest/meta-data/');
    }

    public function testValidateWhitelistBypassesChecks(): void
    {
        // Re-create validator with whitelist enabled
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getBool')->willReturnMap([
            ['security.block_private_ips', true, true],
            ['security.block_local_ips', true, true],
            ['security.block_metadata', true, true],
        ]);
        $configMock->method('getArray')->willReturnMap([
            ['security.url_whitelist', [], ['whitelist.example.com']],
            ['security.url_blacklist', [], []],
        ]);
        
        $validator = new UrlValidator($configMock);
        
        $this->assertTrue($validator->isValid('https://whitelist.example.com'));
        
        // Other URLs should be blocked by the whitelist
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL not in whitelist');
        $validator->validate('https://example.com');
    }

    public function testValidateBlacklistBlocksUrl(): void
    {
        // Re-create validator with blacklist enabled
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getBool')->willReturnMap([
            ['security.block_private_ips', true, true],
            ['security.block_local_ips', true, true],
            ['security.block_metadata', true, true],
        ]);
        $configMock->method('getArray')->willReturnMap([
            ['security.url_whitelist', [], []],
            ['security.url_blacklist', [], ['bad.example.com']],
        ]);
        
        $validator = new UrlValidator($configMock);
        
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL is blacklisted');
        
        $validator->validate('https://bad.example.com/malicious');
    }
}
