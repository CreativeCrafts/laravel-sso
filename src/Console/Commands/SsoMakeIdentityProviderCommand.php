<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Console\Command;

final class SsoMakeIdentityProviderCommand extends Command
{
    protected $signature = 'sso:make-idp {tenant_ulid} {name?} {--protocol=oidc} {--enabled=1}';

    protected $description = 'Create an SSO identity provider for a tenant.';

    public function handle(): int
    {
        $tenantUlid = $this->argument('tenant_ulid');

        if (!is_string($tenantUlid) || $tenantUlid === '') {
            $this->components->error('Tenant ULID must be a non-empty string.');
            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('ulid', $tenantUlid)->first();

        if (!$tenant instanceof Tenant) {
            $this->components->error('Tenant not found for ULID: ' . $tenantUlid);
            return self::FAILURE;
        }

        $name = $this->argument('name') ?? $this->ask('Identity Provider name', 'Default IdP');
        $protocol = $this->option('protocol');
        $enabled = (bool) $this->option('enabled');

        $idp = IdentityProvider::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'protocol' => is_string($protocol) && $protocol !== '' ? $protocol : 'oidc',
            'enabled' => $enabled,
            'config' => [],
        ]);

        $this->components->info("Identity provider created [id: {$idp->id}] for tenant {$tenant->ulid}");

        return self::SUCCESS;
    }
}
