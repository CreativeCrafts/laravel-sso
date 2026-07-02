<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SsoMakeTenantCommand extends Command
{
    protected $signature = 'sso:make-tenant {name?} {--ulid=}';

    protected $description = 'Create an SSO tenant with optional ULID.';

    public function handle(TenantRepository $tenants): int
    {
        $name = $this->argument('name') ?? $this->ask('Tenant name', 'Default Tenant');
        $ulid = $this->option('ulid') ?: (string) Str::ulid();

        if (!Str::isUlid($ulid)) {
            $this->components->error('The provided ULID is invalid.');
            return self::FAILURE;
        }

        $normalizedUlid = TenantRouteKey::normalizeForStorage($ulid);

        if ($tenants->findByUlid($normalizedUlid) instanceof Tenant) {
            $this->components->error("A tenant with ULID [{$normalizedUlid}] already exists.");
            return self::FAILURE;
        }

        $tenant = $tenants->create([
            'name' => $name,
            'ulid' => $normalizedUlid,
            'metadata' => [],
        ]);

        $this->components->info("Tenant created [id: {$tenant->id}, ulid: {$tenant->ulid}]");

        return self::SUCCESS;
    }
}
