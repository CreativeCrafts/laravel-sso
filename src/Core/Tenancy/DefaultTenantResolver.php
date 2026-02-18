<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final class DefaultTenantResolver implements TenantResolver
{
    public function __construct(
        private readonly ?string $defaultTenantUlid,
    ) {
    }

    public function resolve(Request $request): ?Tenant
    {
        if (!is_string($this->defaultTenantUlid) || $this->defaultTenantUlid === '') {
            return null;
        }

        return Tenant::query()
          ->where('ulid', $this->defaultTenantUlid)
          ->first();
    }
}
