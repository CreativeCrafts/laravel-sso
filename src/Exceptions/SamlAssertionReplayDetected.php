<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlAssertionReplayDetected extends RuntimeException
{
    public static function make(): self
    {
        return new self('SAML assertion has already been consumed.');
    }
}
