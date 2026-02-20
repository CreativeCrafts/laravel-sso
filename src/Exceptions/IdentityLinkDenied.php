<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class IdentityLinkDenied extends RuntimeException
{
    public static function make(): self
    {
        return new self('Identity linking is not allowed by policy.');
    }
}
