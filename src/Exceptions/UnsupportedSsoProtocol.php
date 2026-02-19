<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class UnsupportedSsoProtocol extends RuntimeException
{
    public static function for(string $protocol): self
    {
        return new self("Unsupported SSO protocol [{$protocol}].");
    }
}
