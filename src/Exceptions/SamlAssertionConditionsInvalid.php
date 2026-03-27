<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlAssertionConditionsInvalid extends RuntimeException
{
    public static function audienceMismatch(): self
    {
        return new self('SAML assertion audience is invalid.');
    }

    public static function recipientMismatch(): self
    {
        return new self('SAML assertion recipient is invalid.');
    }

    public static function destinationMismatch(): self
    {
        return new self('SAML response destination is invalid.');
    }

    public static function notYetValid(): self
    {
        return new self('SAML assertion is not yet valid (NotBefore).');
    }

    public static function expired(): self
    {
        return new self('SAML assertion is expired (NotOnOrAfter).');
    }

    public static function invalidTimestamp(): self
    {
        return new self('SAML assertion timestamp could not be parsed.');
    }
}
