<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Collection;

interface ConnectionRepository
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(Tenant $tenant, array $attributes): Connection;

    /**
     * @return Collection<int, Connection>
     */
    public function listForTenant(Tenant $tenant): Collection;

    public function findForTenant(Tenant $tenant, int $id): ?Connection;

    public function findForTenantByRouteKey(Tenant $tenant, string $routeKey): ?Connection;

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateForTenant(Tenant $tenant, int $id, array $attributes): Connection;

    public function deleteForTenant(Tenant $tenant, int $id): void;
}
