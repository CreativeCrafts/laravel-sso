<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;

interface TenantRepository
{
    public function findByUlid(string $ulid): ?Tenant;

    /**
     * @throws TenantNotFound
     */
    public function getByUlid(string $ulid): Tenant;
}
