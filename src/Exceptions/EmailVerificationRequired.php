<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class EmailVerificationRequired extends RuntimeException
{
    public static function forLinking(): self
    {
        return new self('Identity linking requires a verified email claim.');
    }

    public static function forProvisioning(): self
    {
        return new self('User provisioning requires a verified email claim.');
    }
}
