<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class HeaderTenantResolver implements TenantResolver
{
    public function __construct(
        private bool $enabled,
        private string $headerName,
        private TenantRepository $tenants,
    ) {
    }

    public function resolve(Request $request): ?Tenant
    {
        if ($this->enabled === false) {
            return null;
        }

        $name = trim($this->headerName);

        if ($name === '') {
            throw TenantResolutionFailed::misconfigured('tenancy.header.name is empty.');
        }

        $value = $request->headers->get($name);

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $this->tenants->findByUlid($value);
    }
}
