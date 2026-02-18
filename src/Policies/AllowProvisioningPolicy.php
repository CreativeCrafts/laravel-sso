<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;

final class AllowProvisioningPolicy implements ProvisioningPolicy
{
    public function shouldProvision(Tenant $tenant, Connection $connection, array $claims): bool
    {
        return true;
    }
}
