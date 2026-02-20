<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class OidcEndpointResolutionFailed extends RuntimeException
{
    public static function missingConfig(): self
    {
        return new self('OIDC endpoints could not be resolved. Provide manual endpoints or enable discovery with issuer/discovery_url.');
    }
}
