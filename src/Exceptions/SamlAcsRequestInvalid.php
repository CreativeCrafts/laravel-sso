<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlAcsRequestInvalid extends RuntimeException
{
    public static function missingResponse(): self
    {
        return new self('SAML ACS request missing SAMLResponse.');
    }

    public static function invalidBase64(): self
    {
        return new self('SAMLResponse is not valid base64.');
    }

    public static function invalidXml(): self
    {
        return new self('SAMLResponse is not valid XML.');
    }
}
