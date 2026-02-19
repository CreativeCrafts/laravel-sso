<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class EloquentIdentityProviderRepository implements IdentityProviderRepository
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(Tenant $tenant, array $attributes): IdentityProvider
    {
        /** @var array<string, mixed> $payload */
        $payload = Arr::except($attributes, ['tenant_id']);

        return IdentityProvider::query()->create([
          ...$payload,
          'tenant_id' => $tenant->id,
        ]);
    }

    public function listForTenant(Tenant $tenant): Collection
    {
        return IdentityProvider::query()
          ->where('tenant_id', $tenant->id)
          ->orderBy('id')
          ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateForTenant(Tenant $tenant, int $id, array $attributes): IdentityProvider
    {
        $model = $this->findForTenant($tenant, $id);

        if (!$model instanceof IdentityProvider) {
            throw TenantScopedRecordNotFound::for(IdentityProvider::class, $id);
        }

        /** @var array<string, mixed> $payload */
        $payload = Arr::except($attributes, ['tenant_id']);

        $model->fill($payload);
        $model->save();

        return $model;
    }

    public function findForTenant(Tenant $tenant, int $id): ?IdentityProvider
    {
        return IdentityProvider::query()
          ->where('tenant_id', $tenant->id)
          ->whereKey($id)
          ->first();
    }

    public function deleteForTenant(Tenant $tenant, int $id): void
    {
        $model = $this->findForTenant($tenant, $id);

        if (!$model instanceof IdentityProvider) {
            throw TenantScopedRecordNotFound::for(IdentityProvider::class, $id);
        }

        $model->delete();
    }
}
