<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Exceptions;

use RuntimeException;

final class UserEmailAlreadyExists extends RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self("A user with email [{$email}] already exists.");
    }
}
