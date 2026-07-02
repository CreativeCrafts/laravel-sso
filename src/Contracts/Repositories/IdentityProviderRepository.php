<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Collection;

interface IdentityProviderRepository
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(Tenant $tenant, array $attributes): IdentityProvider;

    /**
     * @return Collection<int, IdentityProvider>
     */
    public function listForTenant(Tenant $tenant): Collection;

    public function findForTenant(Tenant $tenant, int $id): ?IdentityProvider;

    public function findForTenantByRouteKey(Tenant $tenant, string $routeKey): ?IdentityProvider;

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateForTenant(Tenant $tenant, int $id, array $attributes): IdentityProvider;

    public function deleteForTenant(Tenant $tenant, int $id): void;
}
