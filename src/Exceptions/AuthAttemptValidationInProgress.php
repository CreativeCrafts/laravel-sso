<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class AuthAttemptValidationInProgress extends RuntimeException
{
    public static function forState(string $state): self
    {
        return new self("Auth attempt validation is already in progress for state [{$state}].");
    }
}
