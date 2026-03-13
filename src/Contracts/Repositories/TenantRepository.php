<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Collection;

interface TenantRepository
{
    public function findByUlid(string $ulid): ?Tenant;

    /**
     * @throws TenantNotFound
     */
    public function getByUlid(string $ulid): Tenant;

    public function findByHost(string $host): ?Tenant;

    public function findBySubdomain(string $subdomain): ?Tenant;

    /**
     * @return Collection<int, Tenant>
     */
    public function listAll(): Collection;

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Tenant;

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws TenantNotFound
     */
    public function updateByUlid(string $ulid, array $attributes): Tenant;

    /**
     * @throws TenantNotFound
     */
    public function deleteByUlid(string $ulid): void;
}
