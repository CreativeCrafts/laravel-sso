<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SsoMakeTenantCommand extends Command
{
    protected $signature = 'sso:make-tenant {name?} {--ulid=}';

    protected $description = 'Create an SSO tenant with optional ULID.';

    public function handle(): int
    {
        $name = $this->argument('name') ?? $this->ask('Tenant name', 'Default Tenant');
        $ulid = $this->option('ulid') ?: (string) Str::ulid();

        $tenant = Tenant::query()->create([
            'name' => $name,
            'ulid' => $ulid,
            'metadata' => [],
        ]);

        $this->components->info("Tenant created [id: {$tenant->id}, ulid: {$tenant->ulid}]");

        return self::SUCCESS;
    }
}
