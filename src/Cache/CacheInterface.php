<?php

declare(strict_types=1);

namespace TinyProxy\Cache;

/**
 * Cache interface
 */
interface CacheInterface
{
    public function get(string $key): ?CachedContent;
    public function set(string $key, CachedContent $content): void;
    public function has(string $key): bool;
    public function delete(string $key): void;
    public function clear(): void;
}
