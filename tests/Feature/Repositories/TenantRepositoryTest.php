<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
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

it('lists creates updates and deletes tenants through the repository', function () {
    $repository = new EloquentTenantRepository();

    $created = $repository->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant Created',
        'metadata' => ['domain' => 'created.example.test'],
    ]);

    $listed = $repository->listAll();

    expect($listed->pluck('id')->all())->toContain($created->id);

    $updated = $repository->updateByUlid($created->ulid, [
        'name' => 'Tenant Updated',
        'metadata' => ['domain' => 'updated.example.test'],
    ]);

    expect($updated->id)->toBe($created->id)
        ->and($updated->name)->toBe('Tenant Updated')
        ->and($updated->metadata['domain'])->toBe('updated.example.test');

    $repository->deleteByUlid($created->ulid);

    expect($repository->findByUlid($created->ulid))->toBeNull();
});

it('throws when updating or deleting a missing tenant', function () {
    $repository = new EloquentTenantRepository();
    $missing = (string) Str::ulid();

    expect(fn () => $repository->updateByUlid($missing, ['name' => 'Nope']))
        ->toThrow(TenantNotFound::class);

    expect(fn () => $repository->deleteByUlid($missing))
        ->toThrow(TenantNotFound::class);
});

it('finds tenants by ulid using case insensitive matching', function () {
    $repository = new EloquentTenantRepository();

    $tenant = Tenant::query()->create([
        'ulid' => '01arz3ndektsv4rrffq69g5fav',
        'name' => 'Lowercase ULID Tenant',
    ]);

    expect($repository->findByUlid('01ARZ3NDEKTSV4RRFFQ69G5FAV')?->id)->toBe($tenant->id)
        ->and($repository->findByUlid('01arz3ndektsv4rrffq69g5fav')?->id)->toBe($tenant->id);
});
