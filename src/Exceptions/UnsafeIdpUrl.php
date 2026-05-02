<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class UnsafeIdpUrl extends RuntimeException
{
    public static function forField(string $field, string $reason): self
    {
        return new self("Unsafe IdP URL [{$field}]: {$reason}");
    }
}
