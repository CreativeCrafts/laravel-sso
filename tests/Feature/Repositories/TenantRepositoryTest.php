<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentTenantRepository;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('finds tenants by ulid host and subdomain through the repository', function () {
    $repository = new EloquentTenantRepository();

    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant A',
        'metadata' => [
            'domain' => 'tenant-a.example.test',
            'domains' => [
                'login.tenant-a.example.test',
                'sso.tenant-a.example.test',
            ],
            'subdomain' => 'tenant-a',
        ],
    ]);

    expect($repository->findByUlid($tenant->ulid)?->id)->toBe($tenant->id)
        ->and($repository->findByHost('tenant-a.example.test')?->id)->toBe($tenant->id)
        ->and($repository->findByHost('login.tenant-a.example.test')?->id)->toBe($tenant->id)
        ->and($repository->findBySubdomain('tenant-a')?->id)->toBe($tenant->id)
        ->and($repository->findByHost('missing.example.test'))->toBeNull()
        ->and($repository->findBySubdomain('missing'))->toBeNull();
});
