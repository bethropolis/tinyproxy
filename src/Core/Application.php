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

        ob_start();
        require dirname(__DIR__, 2) . '/templates/home.php';
        return ob_get_clean() ?: '';
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

        ob_start();
        require dirname(__DIR__, 2) . '/templates/error.php';
        return ob_get_clean() ?: '';
    }

    /**
     * Render admin dashboard
     */
    private function renderAdminDashboard(): string
    {
        $stats = $this->proxyService->getStatistics();

        ob_start();
        require dirname(__DIR__, 2) . '/templates/admin.php';
        return ob_get_clean() ?: '';
    }
}
