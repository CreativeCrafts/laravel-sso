<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class ProvisioningDenied extends RuntimeException
{
    public static function make(): self
    {
        return new self('Provisioning is not allowed by policy.');
    }
}
