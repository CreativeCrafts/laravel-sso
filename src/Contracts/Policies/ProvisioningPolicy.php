<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Policies;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;

interface ProvisioningPolicy
{
    /**
     * @param array<string, mixed> $claims
     */
    public function shouldProvision(Tenant $tenant, Connection $connection, array $claims): bool;
}
