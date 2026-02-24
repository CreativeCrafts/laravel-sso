<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class HostTenantResolver implements TenantResolver
{
    public function __construct(
        private bool $enabled,
        private string $mode,
        private ?string $baseDomain,
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
        // metadata->domain: "tenant.example.com"
        $tenant = Tenant::query()
          ->where('metadata->domain', $host)
          ->first();

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        // metadata->domains: ["a.example.com", "b.example.com"]
        return Tenant::query()
          ->whereJsonContains('metadata->domains', $host)
          ->first();
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

        $sub = substr($host, 0, -strlen($suffix));

        if ($sub === '' || str_contains($sub, '.')) {
            // Only single-level subdomains supported (e.g., tenant.example.com).
            return null;
        }

        // Prefer explicit metadata mapping, fall back to tenant ulid as a subdomain.
        $tenant = Tenant::query()
          ->where('metadata->subdomain', $sub)
          ->first();

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return Tenant::query()
          ->where('ulid', $sub)
          ->first();
    }
}
