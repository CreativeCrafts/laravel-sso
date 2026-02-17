<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class TenantScopedRecordNotFound extends RuntimeException
{
    public static function for(string $model, int|string $key): self
    {
        return new self("Record not found for {$model} [{$key}] within tenant scope.");
    }
}
