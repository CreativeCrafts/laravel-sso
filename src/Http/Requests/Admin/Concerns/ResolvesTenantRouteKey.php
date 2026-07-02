<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns;

use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Models\Tenant;

trait ResolvesTenantRouteKey
{
    protected function resolveRouteTenant(): ?Tenant
    {
        $tenantUlid = $this->route('tenant');

        if (!is_string($tenantUlid) || trim($tenantUlid) === '') {
            return null;
        }

        return app(TenantRepository::class)->findByUlid($tenantUlid);
    }

    protected function normalizedRouteTenantUlid(): ?string
    {
        $tenantUlid = $this->route('tenant');

        if (!is_string($tenantUlid) || trim($tenantUlid) === '') {
            return null;
        }

        return TenantRouteKey::normalizeForStorage($tenantUlid);
    }
}
