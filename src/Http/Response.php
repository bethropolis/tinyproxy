<?php

declare(strict_types=1);

namespace TinyProxy\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Response wrapper
 */
class Response
{
    private int $statusCode;
    private array $headers;
    private string $body;

    public function __construct(
        ResponseInterface|string $response,
        ?int $statusCode = null,
        ?array $headers = null
    ) {
        if ($response instanceof ResponseInterface) {
            $this->statusCode = $response->getStatusCode();
            $this->headers = $this->parseHeaders($response);
            $this->body = (string) $response->getBody();
        } else {
            $this->body = $response;
            $this->statusCode = $statusCode ?? 200;
            $this->headers = $headers ?? [];
        }
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response body
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get single header value
     */
    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $name) {
                return is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return null;
    }

    /**
     * Get content type
     */
    public function getContentType(): string
    {
        $contentType = $this->getHeader('Content-Type') ?? 'text/html';
        
        // Remove charset if present
        if (str_contains($contentType, ';')) {
            $parts = explode(';', $contentType);
            $contentType = trim($parts[0]);
        }

        return $contentType;
    }

    /**
     * Check if response is successful (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Send response to browser
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    header("{$name}: {$v}", false);
                }
            } else {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }

    /**
     * Parse headers from PSR-7 response
     */
    private function parseHeaders(ResponseInterface $response): array
    {
        $headers = [];
        
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $headers;
    }
}
