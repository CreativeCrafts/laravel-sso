<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class HostTenantResolver implements TenantResolver
{
    public function __construct(
        private bool $enabled,
        private string $mode,
        private ?string $baseDomain,
        private TenantRepository $tenants,
    ) {
    }

    public function resolve(Request $request): ?Tenant
    {
        if ($this->enabled === false) {
            return null;
        }

        $host = $request->getHost();

        if ($host === '') {
            return null;
        }

        return match ($this->mode) {
            'host' => $this->resolveByHost($host),
            'subdomain' => $this->resolveBySubdomain($host),
            default => throw TenantResolutionFailed::misconfigured('tenancy.host.mode must be host|subdomain.'),
        };
    }

    private function resolveByHost(string $host): ?Tenant
    {
        return $this->tenants->findByHost($host);
    }

    private function resolveBySubdomain(string $host): ?Tenant
    {
        $base = $this->baseDomain;

        if (!is_string($base) || trim($base) === '') {
            throw TenantResolutionFailed::misconfigured('tenancy.host.base_domain is required for subdomain mode.');
        }

        $base = strtolower(ltrim(trim($base), '.'));
        $host = strtolower($host);

        if ($host === $base) {
            return null;
        }

        $suffix = '.' . $base;

        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        $tenant = $this->tenants->findBySubdomain($subdomain);

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return $this->tenants->findByUlid($subdomain);
    }
}
