<?php

declare(strict_types=1);

namespace TinyProxy\Core;

use TinyProxy\Cache\CacheInterface;
use TinyProxy\Cache\CachedContent;
use TinyProxy\Config\Configuration;
use TinyProxy\Exception\ProxyException;
use TinyProxy\Exception\SecurityException;
use TinyProxy\Http\Client;
use TinyProxy\Http\Response;
use TinyProxy\Logger\LoggerInterface;
use TinyProxy\Logger\RequestLogger;
use TinyProxy\Modifier\ModifierInterface;
use TinyProxy\Security\AccessControl;
use TinyProxy\Security\RateLimiter;
use TinyProxy\Security\UrlValidator;
use TinyProxy\Statistics\StatsCollector;

/**
 * Core proxy service that orchestrates all proxy operations
 *
 * This is the main service that coordinates:
 * - Security validation (SSRF prevention, rate limiting, access control)
 * - Cache management (lookup, store, invalidate)
 * - HTTP request proxying
 * - Content modification (HTML/CSS rewriting, ad blocking)
 * - Statistics collection
 * - Request logging
 */
class ProxyService
{
    /**
     * @param ModifierInterface[] $modifiers
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly UrlValidator $urlValidator,
        private readonly RateLimiter $rateLimiter,
        private readonly AccessControl $accessControl,
        private readonly CacheInterface $cache,
        private readonly Client $httpClient,
        private readonly array $modifiers,
        private readonly StatsCollector $stats,
        private readonly LoggerInterface $logger,
        private readonly RequestLogger $requestLogger
    ) {
    }

    /**
     * Proxy a URL and return the response
     *
     * @throws ProxyException If proxying fails
     * @throws SecurityException If security checks fail
     */
    public function proxy(string $url, ?string $apiKey = null, ?string $clientIp = null): Response
    {
        $startTime = microtime(true);
        $clientIp = $clientIp ?? $this->getClientIp();

        try {
            // Security checks
            $this->performSecurityChecks($url, $apiKey, $clientIp);

            // Check cache first
            if ($this->config->getBool('cache.enabled', true)) {
                $cached = $this->cache->get($url);
                if ($cached !== null) {
                    $this->logger->info('Cache hit for URL', ['url' => $url]);
                    $this->stats->recordRequest($url, true, microtime(true) - $startTime, $clientIp);
                    $this->logRequest($url, 200, $cached->getSize(), microtime(true) - $startTime, true, $clientIp);

                    return $this->createResponseFromCache($cached);
                }
            }

            // Fetch content from remote server
            $this->logger->info('Fetching URL', ['url' => $url]);
            $response = $this->httpClient->get($url);

            // Apply content modifiers
            $modifiedResponse = $this->applyModifiers($response, $url);

            // Cache the response
            if ($this->config->getBool('cache.enabled', true) && $this->shouldCache($modifiedResponse)) {
                $this->cacheResponse($url, $modifiedResponse);
            }

            // Record statistics
            $this->stats->recordRequest($url, false, microtime(true) - $startTime, $clientIp);
            $this->logRequest(
                $url,
                $modifiedResponse->getStatusCode(),
                strlen($modifiedResponse->getBody()),
                microtime(true) - $startTime,
                false,
                $clientIp
            );

            return $modifiedResponse;
        } catch (SecurityException $e) {
            $this->logger->warning('Security check failed', [
                'url' => $url,
                'ip' => $clientIp,
                'error' => $e->getMessage()
            ]);
            $this->stats->recordError($url, 'security', $clientIp);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Proxy request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->stats->recordError($url, 'proxy', $clientIp);
            throw new ProxyException(
                sprintf('Failed to proxy URL: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Invalidate cache for a specific URL or pattern
     */
    public function invalidateCache(?string $pattern = null): int
    {
        if ($pattern === null) {
            $this->cache->clear();
            $this->logger->info('Cache cleared completely');
            return -1; // Unknown count
        }

        $count = $this->cache->deleteByPattern($pattern);
        $this->logger->info('Cache invalidated', ['pattern' => $pattern, 'count' => $count]);
        return $count;
    }

    /**
     * Get proxy statistics
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return $this->stats->getStats();
    }

    /**
     * Perform all security checks
     *
     * @throws SecurityException
     */
    private function performSecurityChecks(string $url, ?string $apiKey, string $clientIp): void
    {
        // Validate URL format and check for SSRF
        if (!$this->urlValidator->isValid($url)) {
            throw new SecurityException('Invalid or forbidden URL');
        }

        // Check access control (API key or IP whitelist)
        if (!$this->accessControl->isAllowed($apiKey, $clientIp)) {
            throw new SecurityException('Access denied');
        }

        // Check rate limits
        if (!$this->rateLimiter->allowRequest($clientIp)) {
            $limits = $this->rateLimiter->getLimits($clientIp);
            throw new SecurityException(
                sprintf(
                    'Rate limit exceeded. Try again in %d seconds.',
                    $limits['reset_in'] ?? 60
                )
            );
        }
    }

    /**
     * Apply all configured content modifiers
     */
    private function applyModifiers(Response $response, string $url): Response
    {
        $contentType = $response->getHeader('Content-Type') ?? '';
        $body = $response->getBody();

        foreach ($this->modifiers as $modifier) {
            if ($modifier->supports($contentType)) {
                $this->logger->debug('Applying modifier', [
                    'modifier' => get_class($modifier),
                    'content_type' => $contentType
                ]);
                $body = $modifier->modify($body, $url);
            }
        }

        return new Response(
            $body,
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    /**
     * Determine if response should be cached
     */
    private function shouldCache(Response $response): bool
    {
        // Only cache successful responses
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        // Don't cache if response has no-cache headers
        $cacheControl = strtolower($response->getHeader('Cache-Control') ?? '');
        if (str_contains($cacheControl, 'no-cache') || str_contains($cacheControl, 'no-store')) {
            return false;
        }

        // Don't cache if content is too large
        $maxSize = $this->config->getInt('cache.max_entry_size', 10485760); // 10MB default
        if (strlen($response->getBody()) > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $url, Response $response): void
    {
        $ttl = $this->determineTtl($response);
        $contentType = $response->getHeader('Content-Type') ?? 'application/octet-stream';

        $cachedContent = new CachedContent(
            content: $response->getBody(),
            contentType: $contentType,
            createdAt: time(),
            size: strlen($response->getBody()),
            statusCode: $response->getStatusCode(),
            headers: $response->getHeaders(),
            compressed: false
        );

        $this->cache->set($url, $cachedContent, $ttl);
        $this->logger->debug('Response cached', ['url' => $url, 'ttl' => $ttl]);
    }

    /**
     * Determine TTL based on response headers and configuration
     */
    private function determineTtl(Response $response): int
    {
        $defaultTtl = $this->config->getInt('cache.default_ttl', 3600);

        // Check for Cache-Control max-age
        $cacheControl = $response->getHeader('Cache-Control');
        if ($cacheControl && preg_match('/max-age=(\d+)/', $cacheControl, $matches)) {
            return (int) $matches[1];
        }

        // Check for Expires header
        $expires = $response->getHeader('Expires');
        if ($expires) {
            $expiresTime = strtotime($expires);
            if ($expiresTime !== false) {
                $ttl = $expiresTime - time();
                return max(0, $ttl);
            }
        }

        return $defaultTtl;
    }

    /**
     * Create response from cached content
     */
    private function createResponseFromCache(CachedContent $cached): Response
    {
        $headers = $cached->getHeaders();
        $headers['X-Cache'] = 'HIT';
        $headers['X-Cache-Age'] = (string) $cached->getAge();

        return new Response(
            $cached->getContent(),
            $cached->getStatusCode(),
            $headers
        );
    }

    /**
     * Log the request
     */
    private function logRequest(
        string $url,
        int $statusCode,
        int $size,
        float $duration,
        bool $cached,
        string $clientIp
    ): void {
        $this->requestLogger->log(
            url: $url,
            statusCode: $statusCode,
            duration: $duration,
            cacheHit: $cached,
            ip: $clientIp
        );
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        // Check for proxy headers
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
