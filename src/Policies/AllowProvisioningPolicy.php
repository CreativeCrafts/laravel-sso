<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;

final class AllowProvisioningPolicy implements ProvisioningPolicy
{
    /**
     * @param array<string, mixed> $claims
     */
    public function allows(Tenant $tenant, Connection $connection, IdentityProvider $identityProvider, array $claims): bool
    {
        return true;
    }
}
