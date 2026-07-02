<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlAuthorizationRequestFailed extends RuntimeException
{
    public static function missingConfig(string $field): self
    {
        return new self("SAML authorization request is missing config.{$field}.");
    }

    public static function missingAttemptField(string $field): self
    {
        return new self("SAML authorization request is missing attempt {$field}.");
    }

    public static function missingIdentityProvider(): self
    {
        return new self('SAML identity provider is missing on connection.');
    }

    public static function compressionFailed(): self
    {
        return new self('Unable to compress SAML AuthnRequest.');
    }

    public static function signingFailed(): self
    {
        return new self('Unable to sign SAML AuthnRequest.');
    }

    public static function signingKeysMissing(): self
    {
        return new self('SAML AuthnRequest signing is enabled but signing keys are not configured.');
    }
}
