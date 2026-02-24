<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class SamlClaimsNormalizationFailed extends RuntimeException
{
    public static function missingNameId(): self
    {
        return new self('SAML claims normalization failed: missing NameID (subject).');
    }
}
