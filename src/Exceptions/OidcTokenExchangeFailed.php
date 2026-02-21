<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;
use Throwable;

final class OidcTokenExchangeFailed extends RuntimeException
{
    public static function make(string $reason, ?Throwable $previous = null): self
    {
        return new self("OIDC token exchange failed: {$reason}", 0, $previous);
    }
}
