<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class OidcCallbackCodeMissing extends RuntimeException
{
    public static function make(): self
    {
        return new self('OIDC callback missing authorization code.');
    }
}
