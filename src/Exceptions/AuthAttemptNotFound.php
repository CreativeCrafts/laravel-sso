<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class AuthAttemptNotFound extends RuntimeException
{
    public static function forState(string $state): self
    {
        return new self("Auth attempt not found for state [{$state}].");
    }
}
