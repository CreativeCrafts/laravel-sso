<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class TenantNotFound extends RuntimeException
{
    public static function forUlid(string $ulid): self
    {
        return new self("Tenant not found for ulid [{$ulid}].");
    }
}
