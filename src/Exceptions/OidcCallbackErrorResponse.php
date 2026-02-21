<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class OidcCallbackErrorResponse extends RuntimeException
{
    public static function fromProvider(string $error, ?string $description = null): self
    {
        $suffix = is_string($description) && $description !== '' ? ": {$description}" : '';

        return new self("OIDC provider returned error [{$error}]{$suffix}");
    }
}
