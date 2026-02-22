<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;
use Throwable;

final class SamlMetadataParseFailed extends RuntimeException
{
    public static function invalidXml(?Throwable $previous = null): self
    {
        return new self('SAML metadata XML could not be parsed.', 0, $previous);
    }

    public static function missingEntityId(): self
    {
        return new self('SAML metadata missing required entityID.');
    }

    public static function missingIdpDescriptor(): self
    {
        return new self('SAML metadata missing IDPSSODescriptor.');
    }

    public static function missingSsoService(): self
    {
        return new self('SAML metadata missing SingleSignOnService endpoints.');
    }

    public static function missingSigningCertificate(): self
    {
        return new self('SAML metadata missing signing certificate(s).');
    }
}
