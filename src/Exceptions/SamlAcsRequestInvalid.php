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

    public static function missingRequestId(): self
    {
        return new self('SAML AuthnRequest correlation id is missing.');
    }

    public static function correlationMissing(): self
    {
        return new self('SAMLResponse is missing InResponseTo for correlation.');
    }

    public static function correlationMismatch(): self
    {
        return new self('SAMLResponse InResponseTo does not match the original AuthnRequest.');
    }
}
