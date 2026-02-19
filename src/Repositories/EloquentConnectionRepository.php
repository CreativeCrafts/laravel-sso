<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class EloquentConnectionRepository implements ConnectionRepository
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(Tenant $tenant, array $attributes): Connection
    {
        /** @var array<string, mixed> $payload */
        $payload = Arr::except($attributes, ['tenant_id']);

        return Connection::query()->create([
          ...$payload,
          'tenant_id' => $tenant->id,
        ]);
    }

    public function listForTenant(Tenant $tenant): Collection
    {
        return Connection::query()
          ->where('tenant_id', $tenant->id)
          ->orderBy('id')
          ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateForTenant(Tenant $tenant, int $id, array $attributes): Connection
    {
        $model = $this->findForTenant($tenant, $id);

        if (!$model instanceof Connection) {
            throw TenantScopedRecordNotFound::for(Connection::class, $id);
        }

        /** @var array<string, mixed> $payload */
        $payload = Arr::except($attributes, ['tenant_id']);

        $model->fill($payload);
        $model->save();

        return $model;
    }

    public function findForTenant(Tenant $tenant, int $id): ?Connection
    {
        return Connection::query()
          ->where('tenant_id', $tenant->id)
          ->whereKey($id)
          ->first();
    }

    public function deleteForTenant(Tenant $tenant, int $id): void
    {
        $model = $this->findForTenant($tenant, $id);

        if (!$model instanceof Connection) {
            throw TenantScopedRecordNotFound::for(Connection::class, $id);
        }

        $model->delete();
    }
}
