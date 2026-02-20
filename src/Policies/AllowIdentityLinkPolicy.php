<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

final class AllowIdentityLinkPolicy implements IdentityLinkPolicy
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
    ): bool {
        return true;
    }
}
