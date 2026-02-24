<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class TenantResolutionFailed extends RuntimeException
{
    public static function unableToResolve(): self
    {
        return new self('Unable to resolve tenant from the request.');
    }

    public static function misconfigured(string $reason): self
    {
        return new self('Tenant resolution misconfigured: ' . $reason);
    }
}
