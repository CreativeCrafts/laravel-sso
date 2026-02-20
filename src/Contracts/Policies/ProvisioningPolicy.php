<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Policies;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;

interface ProvisioningPolicy
{
    /**
     * @param array<string, mixed> $claims
     */
    public function allows(Tenant $tenant, Connection $connection, IdentityProvider $identityProvider, array $claims): bool;
}
