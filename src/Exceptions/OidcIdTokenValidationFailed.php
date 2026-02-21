<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class OidcIdTokenValidationFailed extends RuntimeException
{
    public static function make(string $reason): self
    {
        return new self("OIDC id_token validation failed: {$reason}");
    }
}
