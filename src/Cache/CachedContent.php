<?php

declare(strict_types=1);

namespace TinyProxy\Cache;

/**
 * Value object representing cached content
 */
class CachedContent
{
    public function __construct(
        private readonly string $content,
        private readonly string $contentType,
        private readonly int $size,
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
        private readonly bool $compressed = false,
        private readonly int $createdAt = 0,
        private int $lastAccessedAt = 0,
        private int $accessCount = 0
    ) {
        if ($this->createdAt === 0) {
            $this->createdAt = time();
        }
        if ($this->lastAccessedAt === 0) {
            $this->lastAccessedAt = $this->createdAt;
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function isCompressed(): bool
    {
        return $this->compressed;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getLastAccessedAt(): int
    {
        return $this->lastAccessedAt;
    }

    public function getAccessCount(): int
    {
        return $this->accessCount;
    }

    /**
     * Get the age of the cached content in seconds
     */
    public function getAge(): int
    {
        return time() - $this->createdAt;
    }

    public function incrementAccessCount(): void
    {
        $this->accessCount++;
        $this->lastAccessedAt = time();
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'content_type' => $this->contentType,
            'created_at' => $this->createdAt,
            'size' => $this->size,
            'last_accessed_at' => $this->lastAccessedAt,
            'access_count' => $this->accessCount,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'],
            contentType: $data['content_type'],
            createdAt: $data['created_at'],
            size: $data['size'],
            lastAccessedAt: $data['last_accessed_at'] ?? $data['created_at'],
            accessCount: $data['access_count'] ?? 0
        );
    }
}
