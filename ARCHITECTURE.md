# TinyProxy 2.0 - Architecture Design

**Version:** 2.0.0  
**Last Updated:** March 30, 2026

---

## 📐 System Architecture Overview

TinyProxy 2.0 follows a modern, layered architecture with clear separation of concerns, dependency injection, and adherence to SOLID principles.

```
┌─────────────────────────────────────────────────────────────┐
│                       Presentation Layer                     │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  Public Website │  │  Admin Dashboard│  │  REST API   │ │
│  │   (Templates)   │  │   (HTML/JS)     │  │  (JSON)     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────┼────────────────────────────────┐
│                       Application Layer                      │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  ProxyService   │  │  API Controllers│  │  Admin Ctrl │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  CacheManager   │  │  StatsCollector │  │   Router    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────┼────────────────────────────────┐
│                         Domain Layer                         │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  Content        │  │  Security       │  │  HTTP       │ │
│  │  Modifiers      │  │  Services       │  │  Client     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────┼────────────────────────────────┐
│                    Infrastructure Layer                      │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  File Cache     │  │  Logger         │  │  Config     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  APCu Storage   │  │  Guzzle Client  │  │  Container  │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## 🏗️ Architectural Patterns

### 1. Layered Architecture
- **Presentation Layer:** UI, templates, API responses
- **Application Layer:** Business logic orchestration
- **Domain Layer:** Core business logic
- **Infrastructure Layer:** External systems, databases, file systems

### 2. Dependency Injection
- PSR-11 compliant container
- Constructor injection for all dependencies
- Interface-based programming

### 3. Repository Pattern
- Abstraction for data access (cache, logs)
- Swappable implementations (File, Redis, APCu)

### 4. Strategy Pattern
- Content modifiers (HTML, CSS, Images)
- Cache eviction strategies (LRU, FIFO, TTL)

### 5. Middleware Pattern
- Request/response pipeline
- Authentication, rate limiting, CORS

### 6. Factory Pattern
- Creating complex objects (Responses, Cache entries)

---

## 📦 Component Breakdown

### Core Components

#### 1. Bootstrap & Application

```php
namespace TinyProxy;

class Bootstrap
{
    public function createApplication(): Application
    {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        
        // Create and configure container
        $container = new Container();
        $this->registerServices($container);
        
        // Create application
        return new Application($container);
    }
}

class Application
{
    public function __construct(private Container $container) {}
    
    public function run(): void
    {
        $router = $this->container->make(Router::class);
        $response = $router->dispatch($_SERVER['REQUEST_URI']);
        $response->send();
    }
}
```

**Purpose:** Bootstrap the application, set up DI container, handle requests

---

#### 2. Dependency Injection Container

```php
namespace TinyProxy;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    
    public function bind(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }
    
    public function singleton(string $abstract, string|callable $concrete): void
    {
        $this->bind($abstract, $concrete);
        // Store in instances cache after first resolution
    }
    
    public function make(string $abstract): object
    {
        // Resolve with auto-wiring
        // Handle constructor injection
        // Return instance
    }
}
```

**Features:**
- Auto-wiring of dependencies
- Singleton support
- Interface binding
- Lazy loading

---

### Configuration System

```php
namespace TinyProxy\Config;

class Configuration
{
    private array $config = [];
    
    public function __construct()
    {
        $this->loadEnvironment();
        $this->loadConfigFiles();
        $this->validate();
    }
    
    public function getString(string $key, ?string $default = null): string
    {
        return $this->get($key, $default);
    }
    
    public function getInt(string $key, ?int $default = null): int
    {
        return (int) $this->get($key, $default);
    }
    
    public function getBool(string $key, ?bool $default = null): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
    
    public function getArray(string $key, ?array $default = null): array
    {
        $value = $this->get($key, $default);
        return is_string($value) ? explode(',', $value) : $value;
    }
}
```

**Configuration Sources:**
1. Environment variables (`.env`)
2. Config files (`config/*.php`)
3. Default values

---

### Security Layer

#### URL Validator

```php
namespace TinyProxy\Security;

class UrlValidator
{
    private array $privateRanges = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16', // Link-local
    ];
    
    private array $blacklistedDomains = [];
    private array $whitelistedDomains = [];
    
    public function isValid(string $url): bool
    {
        // 1. Parse URL
        // 2. Check scheme (only http/https)
        // 3. Resolve DNS
        // 4. Check if IP is in private range
        // 5. Check blacklist/whitelist
        // 6. Check for DNS rebinding
        return true;
    }
    
    private function isPrivateIp(string $ip): bool
    {
        foreach ($this->privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
}
```

**Security Checks:**
1. Scheme validation (http/https only)
2. DNS resolution
3. Private IP blocking
4. Blacklist/whitelist
5. DNS rebinding protection
6. Redirect limit

---

#### Rate Limiter

```php
namespace TinyProxy\Security;

class RateLimiter
{
    private const KEY_PREFIX = 'rate_limit:';
    
    public function __construct(
        private CacheInterface $cache
    ) {}
    
    public function check(string $identifier, int $limit, int $window): bool
    {
        $key = self::KEY_PREFIX . $identifier;
        $data = $this->cache->get($key) ?? [
            'count' => 0,
            'reset_at' => time() + $window
        ];
        
        // Sliding window algorithm
        if (time() >= $data['reset_at']) {
            $data = ['count' => 0, 'reset_at' => time() + $window];
        }
        
        $data['count']++;
        
        if ($data['count'] > $limit) {
            return false; // Rate limit exceeded
        }
        
        $this->cache->set($key, $data, $window);
        return true;
    }
    
    public function getRemainingAttempts(string $identifier, int $limit): int
    {
        // Get remaining attempts for identifier
    }
}
```

**Algorithm:** Sliding Window Counter  
**Storage:** APCu (fast, in-memory)  
**Features:** Per-IP and per-API-key limits

---

### Cache System

#### Architecture

```
CacheInterface (interface)
    ├── FileCache (implementation)
    ├── RedisCache (future)
    └── MemoryCache (future)

CacheManager
    ├── Uses: CacheInterface
    ├── Manages: Size limits, eviction
    └── Tracks: Statistics

LRUEvictionStrategy
    └── Implements LRU algorithm

CachedContent (Value Object)
    ├── content: string
    ├── contentType: string
    ├── createdAt: int
    ├── lastAccessedAt: int
    ├── accessCount: int
    └── size: int
```

#### FileCache Implementation

```php
namespace TinyProxy\Cache;

class FileCache implements CacheInterface
{
    private const CONTENT_DIR = 'content/';
    private const METADATA_DIR = 'metadata/';
    
    public function __construct(
        private Configuration $config,
        private FileHelper $fileHelper
    ) {}
    
    public function get(string $key): ?CachedContent
    {
        $contentPath = $this->getContentPath($key);
        $metadataPath = $this->getMetadataPath($key);
        
        if (!file_exists($contentPath) || !file_exists($metadataPath)) {
            return null;
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        // Check TTL
        if (time() - $metadata['created_at'] > $this->getTTL($metadata['content_type'])) {
            $this->delete($key);
            return null;
        }
        
        // Decompress and return
        $content = gzdecode(file_get_contents($contentPath));
        
        // Update access metadata
        $metadata['last_accessed_at'] = time();
        $metadata['access_count']++;
        file_put_contents($metadataPath, json_encode($metadata));
        
        return CachedContent::fromArray($metadata + ['content' => $content]);
    }
    
    public function set(string $key, CachedContent $content): void
    {
        // Compress content
        $compressed = gzencode($content->getContent(), 9);
        
        // Save content
        $this->fileHelper->put($this->getContentPath($key), $compressed);
        
        // Save metadata
        $metadata = $content->toArray();
        unset($metadata['content']); // Don't store content in metadata
        $this->fileHelper->put($this->getMetadataPath($key), json_encode($metadata));
    }
}
```

**Features:**
- Gzip compression
- Metadata tracking
- TTL support
- Directory sharding (first 2 chars of hash)

---

#### Cache Manager

```php
namespace TinyProxy\Cache;

class CacheManager
{
    public function __construct(
        private CacheInterface $cache,
        private LRUEvictionStrategy $eviction,
        private CacheStats $stats,
        private Configuration $config
    ) {}
    
    public function get(string $key): ?CachedContent
    {
        $content = $this->cache->get($key);
        
        if ($content === null) {
            $this->stats->recordMiss();
            return null;
        }
        
        $this->stats->recordHit();
        $this->eviction->recordAccess($key);
        
        return $content;
    }
    
    public function set(string $key, CachedContent $content): void
    {
        // Check size limit
        if ($this->stats->getTotalSize() + $content->getSize() > $this->getMaxSize()) {
            $this->evictEntries($content->getSize());
        }
        
        $this->cache->set($key, $content);
        $this->stats->recordWrite($content->getSize());
        $this->eviction->recordAccess($key);
    }
    
    private function evictEntries(int $requiredSpace): void
    {
        $keysToEvict = $this->eviction->getKeysToEvict($requiredSpace);
        
        foreach ($keysToEvict as $key) {
            $this->delete($key);
        }
    }
    
    public function clearByPattern(string $pattern): int
    {
        // Clear cache entries matching pattern
    }
    
    public function clearOlderThan(int $seconds): int
    {
        // Clear entries older than X seconds
    }
}
```

---

### HTTP Layer

#### Client Wrapper

```php
namespace TinyProxy\Http;

use GuzzleHttp\Client as GuzzleClient;

class Client
{
    public function __construct(
        private GuzzleClient $guzzle,
        private UrlValidator $validator,
        private LoggerInterface $logger,
        private Configuration $config
    ) {}
    
    public function get(string $url, array $options = []): Response
    {
        // Validate URL
        if (!$this->validator->isValid($url)) {
            throw new SecurityException("Invalid or prohibited URL: {$url}");
        }
        
        try {
            $response = $this->guzzle->get($url, [
                'timeout' => $this->config->getInt('HTTP_TIMEOUT', 30),
                'allow_redirects' => [
                    'max' => $this->config->getInt('MAX_REDIRECTS', 5),
                    'strict' => true,
                    'track_redirects' => true
                ],
                'headers' => [
                    'User-Agent' => $this->config->getString('USER_AGENT'),
                    'Referer' => $url,
                ],
                'verify' => $this->config->getBool('VERIFY_SSL', true),
            ] + $options);
            
            return new Response($response);
            
        } catch (RequestException $e) {
            $this->logger->error('HTTP request failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new HttpException('Request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
```

---

### Content Modifiers

#### Architecture

```
ModifierInterface
    ├── HtmlModifier
    ├── CssModifier
    ├── AdBlocker
    ├── ImageOptimizer
    └── ContentFilter

ModifierPipeline
    └── Chains modifiers together
```

#### HTML Modifier

```php
namespace TinyProxy\Modifier;

class HtmlModifier implements ModifierInterface
{
    public function __construct(
        private UrlHelper $urlHelper,
        private Configuration $config
    ) {}
    
    public function supports(string $contentType): bool
    {
        return str_starts_with($contentType, 'text/html');
    }
    
    public function modify(string $content, string $baseUrl): string
    {
        if (!$this->config->getBool('HTML_MODIFIER_ENABLED', true)) {
            return $content;
        }
        
        $dom = new \DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Modify URLs in specified attributes
        $this->modifyUrls($dom, $baseUrl);
        
        // Process style tags
        $this->processStyleTags($dom, $baseUrl);
        
        // Add top bar
        if ($this->config->getBool('SHOW_TOP_BAR', true)) {
            $this->addTopBar($dom);
        }
        
        return $dom->saveHTML();
    }
    
    private function modifyUrls(\DOMDocument $dom, string $baseUrl): void
    {
        $attributes = $this->config->getArray('URL_ATTRIBUTES', ['href', 'src', 'action']);
        
        foreach ($dom->getElementsByTagName('*') as $element) {
            foreach ($attributes as $attr) {
                if ($element->hasAttribute($attr)) {
                    $url = $element->getAttribute($attr);
                    $absolute = $this->urlHelper->makeAbsolute($url, $baseUrl);
                    $proxied = $this->urlHelper->makeProxied($absolute);
                    $element->setAttribute($attr, $proxied);
                }
            }
        }
    }
}
```

---

### Proxy Service

```php
namespace TinyProxy\Core;

class ProxyService
{
    public function __construct(
        private Client $httpClient,
        private CacheManager $cacheManager,
        private ModifierPipeline $modifiers,
        private LoggerInterface $logger,
        private RequestLogger $requestLogger,
        private StatsCollector $stats
    ) {}
    
    public function proxyRequest(string $targetUrl, bool $useCache = true): Response
    {
        $startTime = microtime(true);
        $cacheKey = $this->getCacheKey($targetUrl);
        
        // Try cache first
        if ($useCache && $cachedContent = $this->cacheManager->get($cacheKey)) {
            $this->stats->recordCacheHit();
            return $this->createResponse($cachedContent);
        }
        
        // Fetch from origin
        $response = $this->httpClient->get($targetUrl);
        $contentType = $response->getContentType();
        $content = $response->getBody();
        
        // Modify content if applicable
        if ($this->shouldModify($contentType)) {
            $content = $this->modifiers->process($content, $targetUrl, $contentType);
        }
        
        // Cache if applicable
        if ($useCache && $this->shouldCache($contentType, $response)) {
            $cachedContent = new CachedContent(
                content: $content,
                contentType: $contentType,
                createdAt: time(),
                size: strlen($content)
            );
            $this->cacheManager->set($cacheKey, $cachedContent);
        }
        
        // Log request
        $duration = microtime(true) - $startTime;
        $this->requestLogger->log($targetUrl, $response->getStatusCode(), $duration);
        $this->stats->recordRequest($targetUrl, $duration, strlen($content));
        
        return new Response($content, $response->getStatusCode(), [
            'Content-Type' => $contentType
        ]);
    }
    
    private function getCacheKey(string $url): string
    {
        return md5($url);
    }
}
```

---

### API Layer

#### Controller Architecture

```
ApiController (base)
    ├── HealthController
    ├── StatsController
    ├── CacheController
    └── LogController

Middleware Stack:
    1. CorsMiddleware
    2. AuthMiddleware
    3. RateLimitMiddleware
    4. LoggingMiddleware
```

#### Example Controller

```php
namespace TinyProxy\Api\Controller;

class StatsController extends ApiController
{
    public function __construct(
        private StatsCollector $stats,
        private CacheStats $cacheStats
    ) {}
    
    public function getOverallStats(): JsonResponse
    {
        return $this->json([
            'requests' => [
                'total' => $this->stats->getTotalRequests(),
                'last_hour' => $this->stats->getRequestsInWindow(3600),
                'last_24h' => $this->stats->getRequestsInWindow(86400),
            ],
            'cache' => [
                'hits' => $this->cacheStats->getHits(),
                'misses' => $this->cacheStats->getMisses(),
                'hit_rate' => $this->cacheStats->getHitRate(),
                'size' => $this->cacheStats->getTotalSize(),
                'entries' => $this->cacheStats->getEntryCount(),
            ],
            'performance' => [
                'avg_response_time' => $this->stats->getAverageResponseTime(),
                'p95_response_time' => $this->stats->getPercentile(95),
                'p99_response_time' => $this->stats->getPercentile(99),
            ]
        ]);
    }
}
```

---

## 🔄 Request Flow

### 1. Proxy Request Flow

```
User Request
    ↓
Public/index.php (Entry Point)
    ↓
Application::run()
    ↓
Router::dispatch()
    ↓
ProxyService::proxyRequest()
    ├→ Check Cache (CacheManager)
    │   └→ Cache Hit? Return cached content
    ├→ Cache Miss? Fetch from origin
    │   ├→ UrlValidator::isValid()
    │   ├→ RateLimiter::check()
    │   ├→ HttpClient::get()
    │   └→ ModifierPipeline::process()
    │       ├→ HtmlModifier
    │       ├→ CssModifier
    │       └→ AdBlocker
    ├→ Store in Cache
    ├→ Log Request (RequestLogger)
    ├→ Update Stats (StatsCollector)
    └→ Return Response
```

### 2. API Request Flow

```
API Request (with API Key header)
    ↓
Public/api.php (API Entry Point)
    ↓
Middleware Pipeline
    ├→ CorsMiddleware (handle CORS)
    ├→ AuthMiddleware (validate API key/JWT)
    ├→ RateLimitMiddleware (check rate limit)
    └→ LoggingMiddleware (log request)
    ↓
Router::dispatch()
    ↓
Controller Action
    ├→ Process request
    ├→ Call domain services
    └→ Return JsonResponse
    ↓
Response::send()
```

---

## 🗂️ Data Flow

### Cache Data Flow

```
Request for URL
    ↓
Generate cache key (MD5 of URL)
    ↓
Check metadata/index.json for entry
    ↓
Entry exists and not expired?
    ├─Yes→ Read content/[hash].cache.gz
    │       ├→ Decompress
    │       ├→ Update access metadata
    │       └→ Return content
    └─No → Fetch from origin
            ├→ Compress content
            ├→ Write content/[hash].cache.gz
            ├→ Write metadata/[hash].meta.json
            ├→ Update metadata/index.json
            └→ Return content
```

---

## 📊 Database Schema (Optional - for future)

If implementing SQLite for cache metadata:

```sql
CREATE TABLE cache_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    url TEXT NOT NULL,
    content_type TEXT NOT NULL,
    size INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    last_accessed_at INTEGER NOT NULL,
    access_count INTEGER DEFAULT 0,
    expires_at INTEGER NOT NULL,
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_accessed (last_accessed_at)
);

CREATE TABLE request_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    status_code INTEGER,
    response_time REAL,
    cache_hit BOOLEAN,
    created_at INTEGER NOT NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_url (url)
);

CREATE TABLE statistics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    metric TEXT NOT NULL,
    value REAL NOT NULL,
    timestamp INTEGER NOT NULL,
    INDEX idx_metric_timestamp (metric, timestamp)
);
```

---

## 🔐 Security Architecture

### Defense in Depth

```
Layer 1: Network (Future - Firewall rules)
    ↓
Layer 2: Application (Rate Limiting)
    ↓
Layer 3: Authentication (API Keys, JWT)
    ↓
Layer 4: Authorization (RBAC)
    ↓
Layer 5: Input Validation (URL Validator)
    ↓
Layer 6: Output Encoding (Escaping)
    ↓
Layer 7: Logging & Monitoring
```

### SSRF Prevention

```
User Input (URL)
    ↓
1. Parse URL
    ↓
2. Validate scheme (http/https only)
    ↓
3. Resolve DNS
    ↓
4. Check if resolved IP is private
    ├─ 127.0.0.0/8 → BLOCK
    ├─ 10.0.0.0/8 → BLOCK
    ├─ 172.16.0.0/12 → BLOCK
    ├─ 192.168.0.0/16 → BLOCK
    └─ 169.254.0.0/16 → BLOCK
    ↓
5. Check blacklist/whitelist
    ↓
6. Resolve again before request (DNS rebinding protection)
    ↓
7. Make request with strict redirect policy
```

---

## 🧪 Testing Strategy

### Test Pyramid

```
          E2E Tests
         /          \
        /            \
       / Integration  \
      /________________\
     /                  \
    /    Unit Tests      \
   /______________________\
```

### Unit Tests (80%+)
- Test individual classes in isolation
- Mock all dependencies
- Fast execution (<1s total)

### Integration Tests
- Test component interactions
- Use real dependencies where possible
- Test database queries (if applicable)

### E2E Tests (Future)
- Test complete user flows
- Use headless browser
- Test admin dashboard

---

## 📈 Scalability Considerations

### Horizontal Scaling
- Stateless application design
- Shared cache backend (Redis)
- Load balancer compatible

### Vertical Scaling
- Efficient memory usage
- Connection pooling
- OPcache enabled

### Performance Optimization
- HTTP caching headers
- Content compression
- Streaming for large files
- Database query optimization

---

## 🚀 Deployment Architecture

### Production Setup

```
          Internet
              ↓
      ┌──────────────┐
      │ Load Balancer│
      └──────────────┘
              ↓
    ┌─────────┴─────────┐
    ↓                   ↓
┌────────┐          ┌────────┐
│ App #1 │          │ App #2 │
└────────┘          └────────┘
    ↓                   ↓
    └─────────┬─────────┘
              ↓
      ┌──────────────┐
      │ Redis Cache  │
      │  (Shared)    │
      └──────────────┘
```

### Docker Deployment

```yaml
version: '3.8'
services:
  app:
    image: tinyproxy:2.0
    replicas: 3
    environment:
      - APP_ENV=production
      - CACHE_DRIVER=redis
      - REDIS_HOST=redis
    depends_on:
      - redis
  
  redis:
    image: redis:7-alpine
    volumes:
      - redis-data:/data
  
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
```

---

## 🔍 Monitoring & Observability

### Metrics to Track
- Request rate (req/sec)
- Response time (avg, p95, p99)
- Cache hit rate
- Error rate
- Memory usage
- CPU usage
- Disk usage (cache size)

### Logging Strategy
- **Access logs:** All HTTP requests
- **Error logs:** Exceptions and errors
- **Debug logs:** Detailed debugging info (dev only)
- **Audit logs:** Security events (authentication, rate limits)

### Alerting
- Error rate > 5%
- Response time p99 > 1s
- Cache hit rate < 50%
- Disk usage > 90%
- Rate limit violations > 100/min

---

## 📝 Design Decisions & Trade-offs

### 1. File-based Cache vs Redis
**Decision:** Start with file-based, support Redis later  
**Reason:** Simpler deployment, no external dependencies  
**Trade-off:** Less performant at scale, harder to share across instances

### 2. PSR-11 Container vs Framework DI
**Decision:** Custom PSR-11 container  
**Reason:** Lightweight, no framework dependency  
**Trade-off:** Less features than Symfony/Laravel containers

### 3. Templates vs Full Frontend Framework
**Decision:** PHP templates for public, vanilla JS for admin  
**Reason:** Simple, no build step required  
**Trade-off:** Less dynamic UI compared to React/Vue

### 4. SQLite vs MySQL
**Decision:** File-based metadata (optional SQLite later)  
**Reason:** Zero configuration, portable  
**Trade-off:** Limited concurrency, not ideal for high traffic

---

## ✅ Architecture Checklist

### SOLID Principles
- [x] Single Responsibility Principle
- [x] Open/Closed Principle
- [x] Liskov Substitution Principle
- [x] Interface Segregation Principle
- [x] Dependency Inversion Principle

### Design Patterns
- [x] Dependency Injection
- [x] Repository Pattern
- [x] Strategy Pattern
- [x] Factory Pattern
- [x] Middleware/Pipeline Pattern

### Best Practices
- [x] Separation of Concerns
- [x] DRY (Don't Repeat Yourself)
- [x] KISS (Keep It Simple, Stupid)
- [x] YAGNI (You Aren't Gonna Need It)
- [x] Fail Fast

---

**Document Version:** 1.0  
**Last Review:** March 30, 2026  
**Next Review:** After Phase 1 completion
