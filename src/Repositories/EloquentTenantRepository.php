<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Collection;

final class EloquentTenantRepository implements TenantRepository
{
    public function getByUlid(string $ulid): Tenant
    {
        $tenant = $this->findByUlid($ulid);

        if (!$tenant instanceof Tenant) {
            throw TenantNotFound::forUlid($ulid);
        }

        return $tenant;
    }

    public function findByUlid(string $ulid): ?Tenant
    {
        $ulid = trim($ulid);

        if ($ulid === '') {
            return null;
        }

        return Tenant::query()
            ->where('ulid', $ulid)
            ->first();
    }

    public function findByHost(string $host): ?Tenant
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        return Tenant::query()
            ->where('metadata->domain', $host)
            ->orWhereJsonContains('metadata->domains', $host)
            ->first();
    }

    public function findBySubdomain(string $subdomain): ?Tenant
    {
        $subdomain = strtolower(trim($subdomain));

        if ($subdomain === '') {
            return null;
        }

        return Tenant::query()
            ->where('metadata->subdomain', $subdomain)
            ->first();
    }

    public function listAll(): Collection
    {
        return Tenant::query()
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Tenant
    {
        return Tenant::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateByUlid(string $ulid, array $attributes): Tenant
    {
        $tenant = $this->getByUlid($ulid);

        $tenant->fill($attributes);
        $tenant->save();

        return $tenant;
    }

    public function deleteByUlid(string $ulid): void
    {
        $tenant = $this->getByUlid($ulid);

        $tenant->delete();
    }
}
