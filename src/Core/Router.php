<?php

declare(strict_types=1);

namespace TinyProxy\Core;

use TinyProxy\Exception\HttpException;

/**
 * Simple request router for API endpoints and proxy requests
 */
class Router
{
    /** @var array<string, array{pattern: string, handler: callable, methods: string[]}> */
    private array $routes = [];

    /**
     * Add a route
     *
     * @param string[] $methods
     */
    public function add(string $name, string $pattern, callable $handler, array $methods = ['GET']): void
    {
        $this->routes[$name] = [
            'pattern' => $pattern,
            'handler' => $handler,
            'methods' => array_map('strtoupper', $methods),
        ];
    }

    /**
     * Add a GET route
     */
    public function get(string $name, string $pattern, callable $handler): void
    {
        $this->add($name, $pattern, $handler, ['GET']);
    }

    /**
     * Add a POST route
     */
    public function post(string $name, string $pattern, callable $handler): void
    {
        $this->add($name, $pattern, $handler, ['POST']);
    }

    /**
     * Add a route that accepts any method
     */
    public function any(string $name, string $pattern, callable $handler): void
    {
        $this->add($name, $pattern, $handler, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']);
    }

    /**
     * Match a request and return the handler and parameters
     *
     * @return array{handler: callable, params: array<string, string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            // Check if method matches
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            // Convert pattern to regex
            $pattern = $this->convertPatternToRegex($route['pattern']);

            // Try to match
            if (preg_match($pattern, $path, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Dispatch a request
     *
     * @return mixed The result from the handler
     * @throws HttpException If route not found or method not allowed
     */
    public function dispatch(string $method, string $path): mixed
    {
        $match = $this->match($method, $path);

        if ($match === null) {
            // Check if path exists with different method
            if ($this->pathExistsWithDifferentMethod($method, $path)) {
                throw new HttpException('Method not allowed', 405);
            }

            throw new HttpException('Not found', 404);
        }

        return call_user_func($match['handler'], $match['params']);
    }

    /**
     * Convert route pattern to regex
     *
     * Supports:
     * - {name} - required parameter
     * - {name?} - optional parameter
     * - * - wildcard
     */
    private function convertPatternToRegex(string $pattern): string
    {
        // Escape forward slashes
        $pattern = str_replace('/', '\\/', $pattern);

        // Convert {name} to named capture groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $pattern);

        // Convert {name?} to optional named capture groups
        $pattern = preg_replace('/\{(\w+)\?\}/', '(?P<$1>[^\/]+)?', $pattern);

        // Convert * to match anything
        $pattern = str_replace('*', '.*', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Check if path exists with different method
     */
    private function pathExistsWithDifferentMethod(string $method, string $path): bool
    {
        foreach ($this->routes as $route) {
            $pattern = $this->convertPatternToRegex($route['pattern']);

            if (preg_match($pattern, $path)) {
                // Path matches but method doesn't
                if (!in_array(strtoupper($method), $route['methods'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all registered routes
     *
     * @return array<string, array{pattern: string, handler: callable, methods: string[]}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
