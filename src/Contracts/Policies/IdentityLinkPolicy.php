<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Policies;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

interface IdentityLinkPolicy
{
    /**
     * @param array<string, mixed> $claims
     */
    public function allows(
        Tenant $tenant,
        Connection $connection,
        IdentityProvider $identityProvider,
        Authenticatable $user,
        array $claims,
    ): bool;
}
