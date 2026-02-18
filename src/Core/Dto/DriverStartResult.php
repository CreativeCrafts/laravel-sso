<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Dto;

final readonly class DriverStartResult
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $redirectUrl,
        public array $context = [],
    ) {
    }
}
