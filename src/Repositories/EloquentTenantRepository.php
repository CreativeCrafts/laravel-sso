<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;

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

        $tenant = Tenant::query()
            ->where('metadata->domain', $host)
            ->first();

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return Tenant::query()
            ->whereJsonContains('metadata->domains', $host)
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
}
