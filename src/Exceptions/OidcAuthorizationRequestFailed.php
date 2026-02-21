<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class OidcAuthorizationRequestFailed extends RuntimeException
{
    public static function missingConfig(string $key): self
    {
        return new self("OIDC authorization request missing required config [{$key}].");
    }

    public static function missingAttemptField(string $field): self
    {
        return new self("OIDC authorization request missing required auth attempt field [{$field}].");
    }
}
