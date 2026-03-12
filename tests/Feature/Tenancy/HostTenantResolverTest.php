<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\Tenancy\HostTenantResolver;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentTenantRepository;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(TestCase::class);

it('resolves tenant by host via metadata domain', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'HostTenant',
        'metadata' => ['domain' => 'tenant.example.test'],
    ]);

    $resolver = new HostTenantResolver(
        enabled: true,
        mode: 'host',
        baseDomain: null,
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://tenant.example.test/sso/start', 'GET');

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});

it('resolves tenant by host via metadata domains array', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'HostTenant',
        'metadata' => ['domains' => ['a.example.test', 'b.example.test']],
    ]);

    $resolver = new HostTenantResolver(
        enabled: true,
        mode: 'host',
        baseDomain: null,
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://b.example.test/sso/start', 'GET');

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});

it('resolves tenant by subdomain via metadata subdomain', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'SubTenant',
        'metadata' => ['subdomain' => 'acme'],
    ]);

    $resolver = new HostTenantResolver(
        enabled: true,
        mode: 'subdomain',
        baseDomain: 'example.test',
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://acme.example.test/sso/start', 'GET');

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});

it('throws misconfigured exception when subdomain mode has no base domain', function () {
    $resolver = new HostTenantResolver(
        enabled: true,
        mode: 'subdomain',
        baseDomain: null,
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://acme.example.test/sso/start', 'GET');

    expect(fn () => $resolver->resolve($request))
        ->toThrow(TenantResolutionFailed::class);
});
