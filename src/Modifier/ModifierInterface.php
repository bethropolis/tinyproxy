<?php

declare(strict_types=1);

namespace TinyProxy\Modifier;

/**
 * Content modifier interface
 */
interface ModifierInterface
{
    /**
     * Modify content based on content type
     */
    public function modify(string $content, string $baseUrl): string;

    /**
     * Check if modifier supports given content type
     */
    public function supports(string $contentType): bool;
}
