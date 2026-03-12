<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class DefaultTenantResolver implements TenantResolver
{
    public function __construct(
        private ?string $defaultTenantUlid,
        private TenantRepository $tenants,
    ) {
    }

    public function resolve(Request $request): ?Tenant
    {
        if (!is_string($this->defaultTenantUlid) || $this->defaultTenantUlid === '') {
            return null;
        }

        return $this->tenants->findByUlid($this->defaultTenantUlid);
    }
}
