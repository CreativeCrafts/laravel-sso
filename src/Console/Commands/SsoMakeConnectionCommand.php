<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Console\Command;

final class SsoMakeConnectionCommand extends Command
{
    protected $signature = 'sso:make-connection {tenant_ulid} {idp_id} {name?} {--guard=} {--enabled=1}';

    protected $description = 'Create an SSO connection for a tenant and identity provider.';

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

        $idpArg = $this->argument('idp_id');

        if (!is_int($idpArg) && !(is_string($idpArg) && ctype_digit($idpArg))) {
            $this->components->error('Identity provider id must be an integer.');
            return self::FAILURE;
        }

        $idpId = (int) $idpArg;

        $idp = IdentityProvider::query()->where('tenant_id', $tenant->id)->find($idpId);

        if (!$idp instanceof IdentityProvider) {
            $this->components->error('Identity provider not found for this tenant.');
            return self::FAILURE;
        }

        $name = $this->argument('name') ?? $this->ask('Connection name', 'Default Connection');
        $guard = $this->option('guard');
        $enabled = (bool) $this->option('enabled');

        $connection = Connection::query()->create([
            'tenant_id' => $tenant->id,
            'identity_provider_id' => $idp->id,
            'name' => $name,
            'guard' => is_string($guard) && $guard !== '' ? $guard : null,
            'enabled' => $enabled,
            'settings' => [],
        ]);

        $this->components->info("Connection created [id: {$connection->id}] for tenant {$tenant->ulid}");

        return self::SUCCESS;
    }
}
