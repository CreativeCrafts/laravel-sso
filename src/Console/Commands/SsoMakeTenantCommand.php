<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use CreativeCrafts\LaravelSso\Core\TenantRouteKey;

final class SsoMakeTenantCommand extends Command
{
    protected $signature = 'sso:make-tenant {name?} {--ulid=}';

    protected $description = 'Create an SSO tenant with optional ULID.';

    public function handle(): int
    {
        $name = $this->argument('name') ?? $this->ask('Tenant name', 'Default Tenant');
        $ulid = $this->option('ulid') ?: (string) Str::ulid();

        if (!Str::isUlid($ulid)) {
            $this->components->error('The provided ULID is invalid.');
            return self::FAILURE;
        }

        $tenant = Tenant::query()->create([
            'name' => $name,
            'ulid' => TenantRouteKey::normalizeForStorage($ulid),
            'metadata' => [],
        ]);

        $this->components->info("Tenant created [id: {$tenant->id}, ulid: {$tenant->ulid}]");

        return self::SUCCESS;
    }
}
