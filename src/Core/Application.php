<?php

declare(strict_types=1);

namespace TinyProxy\Core;

use TinyProxy\Config\Configuration;
use TinyProxy\Container;
use TinyProxy\Exception\HttpException;
use TinyProxy\Exception\SecurityException;
use TinyProxy\Http\Response;
use TinyProxy\Logger\LoggerInterface;

/**
 * Main application class
 *
 * Handles HTTP requests and coordinates routing
 */
class Application
{
    private Router $router;

    public function __construct(
        private readonly Container $container,
        private readonly Configuration $config,
        private readonly ProxyService $proxyService,
        private readonly LoggerInterface $logger
    ) {
        $this->router = new Router();
        $this->registerRoutes();
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $path = $this->getRequestPath();

            $this->logger->debug('Handling request', ['method' => $method, 'path' => $path]);

            // Try to match route
            $match = $this->router->match($method, $path);

            if ($match !== null) {
                // API endpoint
                $result = call_user_func($match['handler'], $match['params']);
                $this->sendResponse($result);
            } else {
                // Proxy request
                $this->handleProxyRequest();
            }
        } catch (SecurityException $e) {
            $this->handleError($e, 403);
        } catch (HttpException $e) {
            $this->handleError($e, $e->getCode() ?: 500);
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->handleError($e, 500);
        }
    }

    /**
     * Register application routes
     */
    private function registerRoutes(): void
    {
        // Health check endpoint
        $this->router->get('health', '/api/health', function () {
            return [
                'status' => 'ok',
                'timestamp' => time(),
                'version' => $this->config->getString('app.version', '2.0.0'),
            ];
        });

        // Statistics endpoint
        $this->router->get('stats', '/api/stats', function () {
            $this->requireAuth();
            return $this->proxyService->getStatistics();
        });

        // Cache management endpoints
        $this->router->post('cache.clear', '/api/cache/clear', function () {
            $this->requireAuth();
            $pattern = $_POST['pattern'] ?? null;
            $count = $this->proxyService->invalidateCache($pattern);
            return [
                'success' => true,
                'message' => 'Cache cleared',
                'count' => $count,
            ];
        });

        $this->router->get('cache.stats', '/api/cache/stats', function () {
            $this->requireAuth();
            $cache = $this->container->get(\TinyProxy\Cache\CacheInterface::class);
            return $cache->getStats();
        });

        // Admin dashboard
        $this->router->get('admin', '/admin', function () {
            $this->requireAuth();
            return $this->renderAdminDashboard();
        });

        // Proxy endpoint (explicit)
        $this->router->get('proxy', '/proxy', function () {
            $url = $_GET['url'] ?? null;
            if (!$url) {
                throw new HttpException('Missing URL parameter', 400);
            }
            return $this->proxyUrl($url);
        });
    }

    /**
     * Handle proxy request
     */
    private function handleProxyRequest(): void
    {
        // Get URL from query parameter
        $url = $_GET['url'] ?? null;

        if (!$url) {
            $this->sendHtmlResponse($this->renderHomePage(), 200);
            return;
        }

        $response = $this->proxyUrl($url);
        $this->sendResponse($response);
    }

    /**
     * Proxy a URL
     */
    private function proxyUrl(string $url): Response
    {
        $apiKey = $this->getApiKey();
        $clientIp = $this->getClientIp();

        return $this->proxyService->proxy($url, $apiKey, $clientIp);
    }

    /**
     * Get API key from request
     */
    private function getApiKey(): ?string
    {
        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check query parameter
        return $_GET['api_key'] ?? null;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get request path
     */
    private function getRequestPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        return $path;
    }

    /**
     * Require authentication
     *
     * @throws HttpException
     */
    private function requireAuth(): void
    {
        $accessControl = $this->container->get(\TinyProxy\Security\AccessControl::class);
        $apiKey = $this->getApiKey();
        $clientIp = $this->getClientIp();

        if (!$accessControl->isAllowed($apiKey, $clientIp)) {
            throw new HttpException('Authentication required', 401);
        }
    }

    /**
     * Send response to client
     */
    private function sendResponse(mixed $response): void
    {
        if ($response instanceof Response) {
            // HTTP response
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $value) {
                header("$name: $value");
            }

            echo $response->getBody();
        } elseif (is_array($response)) {
            // JSON response
            $this->sendJsonResponse($response);
        } else {
            // String response
            $this->sendHtmlResponse((string) $response);
        }
    }

    /**
     * Send JSON response
     *
     * @param array<string, mixed> $data
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Send HTML response
     */
    private function sendHtmlResponse(string $html, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    /**
     * Handle error
     */
    private function handleError(\Throwable $e, int $statusCode): void
    {
        $message = $this->config->getBool('app.debug', false)
            ? $e->getMessage()
            : 'An error occurred';

        // Send JSON for API endpoints
        $path = $this->getRequestPath();
        if (str_starts_with($path, '/api/')) {
            $this->sendJsonResponse([
                'error' => $message,
                'code' => $statusCode,
            ], $statusCode);
            return;
        }

        // Send HTML for other endpoints
        $html = $this->renderErrorPage($statusCode, $message);
        $this->sendHtmlResponse($html, $statusCode);
    }

    /**
     * Render home page
     */
    private function renderHomePage(): string
    {
        $baseUrl = $this->config->getString('proxy.base_url', '');
        if (!$baseUrl) {
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TinyProxy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        form {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        input[type="url"] {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="url"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        .info {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            font-size: 14px;
            color: #666;
        }
        .info strong {
            color: #333;
        }
        .links {
            margin-top: 20px;
            display: flex;
            gap: 15px;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 TinyProxy</h1>
        <p class="subtitle">Fast, secure, and feature-rich PHP proxy server</p>
        
        <form method="GET" action="/">
            <input type="url" name="url" placeholder="Enter URL to proxy..." required>
            <button type="submit">Go</button>
        </form>
        
        <div class="info">
            <strong>Features:</strong>
            <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                <li>⚡ High-performance caching</li>
                <li>🔒 SSRF prevention & rate limiting</li>
                <li>🎨 Automatic URL rewriting</li>
                <li>📊 Built-in statistics & monitoring</li>
                <li>🛡️ Ad blocking (optional)</li>
            </ul>
        </div>

        <div class="links">
            <a href="/api/health">Health Check</a>
            <a href="/admin">Admin Dashboard</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render error page
     */
    private function renderErrorPage(int $code, string $message): string
    {
        $title = match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title - TinyProxy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        a:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">$code</div>
        <h1>$title</h1>
        <p>$message</p>
        <a href="/">← Back to Home</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render admin dashboard (placeholder)
     */
    private function renderAdminDashboard(): string
    {
        $stats = $this->proxyService->getStatistics();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TinyProxy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 5px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .stat-value {
            color: #333;
            font-size: 32px;
            font-weight: bold;
        }
        .actions {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Admin Dashboard</h1>
        <p class="subtitle">TinyProxy Statistics & Management</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Requests</div>
            <div class="stat-value">{$stats['total_requests']}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Cache Hit Rate</div>
            <div class="stat-value">{$stats['cache_hit_rate']}%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Error Rate</div>
            <div class="stat-value">{$stats['error_rate']}%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg Response Time</div>
            <div class="stat-value">{$stats['avg_response_time']}s</div>
        </div>
    </div>

    <div class="actions">
        <h2 style="margin-bottom: 15px;">Actions</h2>
        <button onclick="clearCache()">Clear Cache</button>
        <button onclick="location.href='/api/stats'">View Full Stats (JSON)</button>
        <button onclick="location.href='/'">Back to Home</button>
    </div>

    <script>
        function clearCache() {
            if (!confirm('Are you sure you want to clear the cache?')) return;
            fetch('/api/cache/clear', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    location.reload();
                })
                .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>
HTML;
    }
}
