<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class RouteParamTenantResolver implements TenantResolver
{
    public function __construct(
        private string $routeParam,
        private TenantRepository $tenants,
    ) {
    }

    public function resolve(Request $request): ?Tenant
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $value = $route->parameter($this->routeParam);

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $this->tenants->findByUlid($value);
    }
}
