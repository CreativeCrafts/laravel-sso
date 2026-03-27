<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Str;

trait SsoTestHelpers
{
    protected function createTenant(string $name = 'Test Tenant'): Tenant
    {
        return Tenant::query()->create([
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'metadata' => [],
        ]);
    }

    protected function createIdentityProvider(Tenant $tenant, string $protocol = 'oidc'): IdentityProvider
    {
        return IdentityProvider::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test IdP',
            'protocol' => $protocol,
            'enabled' => true,
            'config' => [],
        ]);
    }

    protected function createConnection(Tenant $tenant, IdentityProvider $idp): Connection
    {
        return Connection::query()->create([
            'tenant_id' => $tenant->id,
            'identity_provider_id' => $idp->id,
            'name' => 'Test Connection',
            'enabled' => true,
            'guard' => null,
            'settings' => [],
        ]);
    }
}
