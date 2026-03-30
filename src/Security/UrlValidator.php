<?php

declare(strict_types=1);

namespace TinyProxy\Security;

use TinyProxy\Config\Configuration;
use TinyProxy\Exception\SecurityException;
use TinyProxy\Util\UrlHelper;

/**
 * URL validator with SSRF prevention
 */
class UrlValidator
{
    private const PRIVATE_IP_RANGES = [
        '10.0.0.0/8',          // Private network
        '172.16.0.0/12',       // Private network
        '192.168.0.0/16',      // Private network
        '127.0.0.0/8',         // Loopback
        '169.254.0.0/16',      // Link-local
        '::1/128',             // IPv6 loopback
        'fe80::/10',           // IPv6 link-local
        'fc00::/7',            // IPv6 private
    ];

    private const METADATA_ENDPOINTS = [
        '169.254.169.254',     // AWS, Azure, GCP metadata
        '169.254.170.2',       // ECS metadata
        'metadata.google.internal',
        'metadata.azure.com',
    ];

    private array $whitelist = [];
    private array $blacklist = [];
    private bool $blockPrivateIps;
    private bool $blockLocalhost;
    private bool $blockMetadata;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->blockPrivateIps = $config->getBool('security.block_private_ips', true);
        $this->blockLocalhost = $config->getBool('security.block_local_ips', true);
        $this->blockMetadata = $config->getBool('security.block_metadata', true);
        
        $this->whitelist = $config->getArray('security.url_whitelist', []);
        $this->blacklist = $config->getArray('security.url_blacklist', []);
    }

    /**
     * Validate if a URL is safe to access
     */
    public function isValid(string $url): bool
    {
        try {
            $this->validate($url);
            return true;
        } catch (SecurityException $e) {
            return false;
        }
    }

    /**
     * Validate URL and throw exception if invalid
     */
    public function validate(string $url): void
    {
        // Parse URL
        $parsed = parse_url($url);
        
        if ($parsed === false || !isset($parsed['host'])) {
            throw new SecurityException('Invalid URL format');
        }

        // Check scheme
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new SecurityException('Only HTTP and HTTPS schemes are allowed');
        }

        $host = strtolower($parsed['host']);

        // Check whitelist first (if configured)
        if (!empty($this->whitelist)) {
            if (!$this->isInList($host, $this->whitelist)) {
                throw new SecurityException('URL not in whitelist');
            }
            return; // Whitelist bypasses other checks
        }

        // Check blacklist
        if ($this->isInList($host, $this->blacklist)) {
            throw new SecurityException('URL is blacklisted');
        }

        // Check for localhost
        if ($this->blockLocalhost && $this->isLocalhost($host)) {
            throw new SecurityException('Access to localhost is prohibited');
        }

        // Check metadata endpoints
        if ($this->blockMetadata && $this->isMetadataEndpoint($host)) {
            throw new SecurityException('Access to cloud metadata endpoints is prohibited');
        }

        // Resolve DNS and check IP
        $this->validateHost($host);
    }

    /**
     * Validate host by resolving DNS and checking IP
     */
    private function validateHost(string $host): void
    {
        // Check if host is already an IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->validateIp($host);
            return;
        }

        // Resolve DNS
        $ips = $this->resolveDns($host);
        
        if (empty($ips)) {
            throw new SecurityException('Failed to resolve DNS for host');
        }

        // Check all resolved IPs
        foreach ($ips as $ip) {
            $this->validateIp($ip);
        }
    }

    /**
     * Resolve DNS for a hostname
     */
    private function resolveDns(string $host): array
    {
        $ips = [];
        
        // Try IPv4
        $records = @dns_get_record($host, DNS_A);
        if ($records !== false) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        // Try IPv6
        $records = @dns_get_record($host, DNS_AAAA);
        if ($records !== false) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }

    /**
     * Validate if an IP address is allowed
     */
    private function validateIp(string $ip): void
    {
        if (!$this->blockPrivateIps) {
            return;
        }

        // Check if IP is in private ranges
        foreach (self::PRIVATE_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new SecurityException("Access to private IP address {$ip} is prohibited");
            }
        }
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $mask] = explode('/', $range);

        // Handle IPv6
        if (str_contains($ip, ':')) {
            return $this->ipv6InRange($ip, $subnet, (int) $mask);
        }

        // Handle IPv4
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $mask);
        $subnetLong &= $mask;

        return ($ipLong & $mask) === $subnetLong;
    }

    /**
     * Check if IPv6 is in range
     */
    private function ipv6InRange(string $ip, string $subnet, int $mask): bool
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $ipBits = '';
        $subnetBits = '';
        
        $maxLen = max(strlen($ipBin), strlen($subnetBin));
        
        for ($i = 0; $i < $maxLen; $i++) {
            if ($i < strlen($ipBin)) {
                $ipBits .= str_pad(decbin(ord($ipBin[$i])), 8, '0', STR_PAD_LEFT);
            }
            if ($i < strlen($subnetBin)) {
                $subnetBits .= str_pad(decbin(ord($subnetBin[$i])), 8, '0', STR_PAD_LEFT);
            }
        }

        return substr($ipBits, 0, $mask) === substr($subnetBits, 0, $mask);
    }

    /**
     * Check if host is localhost
     */
    private function isLocalhost(string $host): bool
    {
        $localhosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0', '::'];
        return in_array($host, $localhosts, true);
    }

    /**
     * Check if host is a cloud metadata endpoint
     */
    private function isMetadataEndpoint(string $host): bool
    {
        foreach (self::METADATA_ENDPOINTS as $endpoint) {
            if ($host === $endpoint || str_ends_with($host, '.' . $endpoint)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if host matches any pattern in list
     */
    private function isInList(string $host, array $list): bool
    {
        foreach ($list as $pattern) {
            $pattern = trim($pattern);
            if (UrlHelper::matchesPattern($host, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
