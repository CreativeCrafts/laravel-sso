<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use Illuminate\Contracts\Auth\Authenticatable;

interface UserProvisioner
{
    /**
     * @param array<string, mixed> $claims
     */
    public function provision(string $guard, string $email, ?string $displayName, array $claims): Authenticatable;
}
