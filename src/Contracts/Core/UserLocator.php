<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use Illuminate\Contracts\Auth\Authenticatable;

interface UserLocator
{
    public function findByEmail(string $guard, string $email): ?Authenticatable;
}
