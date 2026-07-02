<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlResponseStatusInvalid extends RuntimeException
{
    public static function make(?string $statusCode = null): self
    {
        if (is_string($statusCode) && $statusCode !== '') {
            return new self("SAML response status is not success: {$statusCode}.");
        }

        return new self('SAML response status is not success.');
    }
}
