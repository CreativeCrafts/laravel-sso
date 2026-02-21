<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Dto;

final readonly class DriverCallbackResult
{
    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $context
     */
    public function __construct(
        public bool $authenticated,
        public Claims $canonicalClaims,
        public ?string $subject = null,
        public ?string $email = null,
        public ?string $displayName = null,
        public array $claims = [],
        public array $context = [],
        public ?string $error = null,
    ) {
    }
}
