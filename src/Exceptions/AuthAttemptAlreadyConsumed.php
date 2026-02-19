<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class AuthAttemptAlreadyConsumed extends RuntimeException
{
    public static function forState(string $state): self
    {
        return new self("Auth attempt already consumed for state [{$state}].");
    }
}
