<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class InvalidAuthAttemptBinding extends RuntimeException
{
    public static function connectionMismatch(int $expected, int $actual): self
    {
        return new self("Auth attempt connection mismatch. Expected [{$expected}], got [{$actual}].");
    }

    public static function identityProviderMismatch(int $expected, int $actual): self
    {
        return new self("Auth attempt identity provider mismatch. Expected [{$expected}], got [{$actual}].");
    }
}
