<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;
use Throwable;

final class OidcDiscoveryFailed extends RuntimeException
{
    public static function forUrl(string $url, ?Throwable $previous = null): self
    {
        return new self("OIDC discovery failed for [{$url}].", 0, $previous);
    }
}
