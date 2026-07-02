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

it('returns null when host tenancy is disabled or host is empty', function (): void {
    $resolver = new HostTenantResolver(
        enabled: false,
        mode: 'host',
        baseDomain: null,
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://tenant.example.test/sso/start', 'GET');

    expect($resolver->resolve($request))->toBeNull();
});

it('throws when host tenancy mode is invalid', function (): void {
    $resolver = new HostTenantResolver(
        enabled: true,
        mode: 'invalid',
        baseDomain: null,
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://tenant.example.test/sso/start', 'GET');

    expect(fn () => $resolver->resolve($request))->toThrow(TenantResolutionFailed::class);
});

it('ignores subdomain hosts that do not match the configured base domain', function (): void {
    Tenant::query()->create([
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

    expect($resolver->resolve(Request::create('http://example.test/sso/start', 'GET')))->toBeNull()
        ->and($resolver->resolve(Request::create('http://other.test/sso/start', 'GET')))->toBeNull()
        ->and($resolver->resolve(Request::create('http://nested.acme.example.test/sso/start', 'GET')))->toBeNull();
});

it('resolves subdomain tenants by ulid when metadata subdomain is absent', function (): void {
    $ulid = (string) Str::ulid();
    $tenant = Tenant::query()->create([
        'ulid' => $ulid,
        'name' => 'Ulid Subdomain Tenant',
        'metadata' => [],
    ]);

    $resolver = new HostTenantResolver(
        enabled: true,
        mode: 'subdomain',
        baseDomain: 'example.test',
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('http://' . $ulid . '.example.test/sso/start', 'GET');

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});
