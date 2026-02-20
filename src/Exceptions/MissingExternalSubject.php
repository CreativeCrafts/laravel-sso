<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class MissingExternalSubject extends RuntimeException
{
    public static function make(): self
    {
        return new self('External subject is missing from the driver callback result.');
    }
}
