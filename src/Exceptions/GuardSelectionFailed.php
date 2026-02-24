<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class GuardSelectionFailed extends RuntimeException
{
    public static function notConfigured(string $guard): self
    {
        return new self('Selected auth guard is not configured: ' . $guard);
    }

    public static function notAllowed(string $guard): self
    {
        return new self('Selected auth guard is not allowed: ' . $guard);
    }
}
