<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlSignatureMissing extends RuntimeException
{
    public static function make(): self
    {
        return new self('SAML Response/Assertion signature is missing.');
    }
}
