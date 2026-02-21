<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Dto;

final readonly class Claims
{
    /**
     * @param array<int, string> $groups
     * @param array<string, mixed> $normalized
     */
    public function __construct(
        public string $subject,
        public ?string $email,
        public ?string $displayName,
        public ?bool $emailVerified,
        public array $groups,
        public array $normalized,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->normalized;
    }
}
