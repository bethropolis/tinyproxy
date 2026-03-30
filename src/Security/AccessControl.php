<?php

declare(strict_types=1);

namespace TinyProxy\Security;

use TinyProxy\Config\Configuration;
use TinyProxy\Exception\SecurityException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Access control and authentication
 */
class AccessControl
{
    private bool $enabled;
    private bool $requireApiKey;
    private array $apiKeys = [];
    private string $jwtSecret;
    private string $jwtAlgorithm;

    public function __construct(
        private readonly Configuration $config
    ) {
        $this->enabled = $config->getBool('security.require_auth', false);
        $this->requireApiKey = $config->getBool('security.require_api_key', false);
        
        $this->apiKeys = $config->getArray('security.api_keys', []);
        
        $this->jwtSecret = $config->getString('security.jwt_secret', 'changeme');
        $this->jwtAlgorithm = $config->getString('security.jwt_algorithm', 'HS256');

        // Warn if using default JWT secret in production
        if ($this->jwtSecret === 'changeme' && $config->isProduction()) {
            error_log('WARNING: Using default JWT secret in production! Please change JWT_SECRET in .env');
        }
    }

    /**
     * Check if request is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (!$this->enabled) {
            return true;
        }

        // Check API key first
        if ($this->requireApiKey) {
            return $this->validateApiKey();
        }

        // Check JWT token
        return $this->validateJwt();
    }

    /**
     * Authenticate request and throw exception if invalid
     */
    public function authenticate(): void
    {
        if (!$this->isAuthenticated()) {
            throw new SecurityException('Authentication required');
        }
    }

    /**
     * Validate API key from request
     */
    private function validateApiKey(): bool
    {
        $apiKey = $this->getApiKeyFromRequest();
        
        if ($apiKey === null) {
            return false;
        }

        return in_array($apiKey, $this->apiKeys, true);
    }

    /**
     * Get API key from request headers
     */
    private function getApiKeyFromRequest(): ?string
    {
        // Check X-API-Key header
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        // Check Authorization header (Bearer token)
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (str_starts_with($auth, 'Bearer ')) {
                return substr($auth, 7);
            }
        }

        return null;
    }

    /**
     * Validate JWT token
     */
    private function validateJwt(): bool
    {
        $token = $this->getJwtFromRequest();
        
        if ($token === null) {
            return false;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get JWT token from request
     */
    private function getJwtFromRequest(): ?string
    {
        // Check Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (str_starts_with($auth, 'Bearer ')) {
                return substr($auth, 7);
            }
        }

        // Check cookie
        if (isset($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }

        return null;
    }

    /**
     * Generate JWT token
     */
    public function generateToken(array $payload, ?int $expiration = null): string
    {
        $expiration = $expiration ?? $this->config->getInt('JWT_EXPIRATION', 86400);
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiration;

        return JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * Decode JWT token
     */
    public function decodeToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate API key
     */
    public static function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Check if IP is allowed (for IP whitelisting)
     */
    public function isIpAllowed(?string $ip = null): bool
    {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? null;
        
        if ($ip === null) {
            return false;
        }

        // Get IP whitelist/blacklist from config
        $whitelist = $this->config->getArray('security.ip_whitelist', []);
        $blacklist = $this->config->getArray('security.ip_blacklist', []);

        // Check blacklist first
        if (in_array($ip, $blacklist, true)) {
            return false;
        }

        // If whitelist is configured, check it
        if (!empty($whitelist)) {
            return in_array($ip, $whitelist, true);
        }

        // No whitelist configured, allow by default
        return true;
    }

    /**
     * Check if request is allowed (combines authentication and IP check)
     */
    public function isAllowed(?string $apiKey = null, ?string $clientIp = null): bool
    {
        // Check IP first
        if (!$this->isIpAllowed($clientIp)) {
            return false;
        }

        // If auth is not required, allow
        if (!$this->enabled) {
            return true;
        }

        // Check authentication
        if ($apiKey !== null) {
            // Validate the provided API key
            return in_array($apiKey, $this->apiKeys, true);
        }

        return $this->isAuthenticated();
    }
}
