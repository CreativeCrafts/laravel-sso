<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\Tenancy\HeaderTenantResolver;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentTenantRepository;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(TestCase::class);

it('resolves tenant from header when enabled', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'HeaderTenant']);

    $resolver = new HeaderTenantResolver(
        enabled: true,
        headerName: 'X-SSO-Tenant',
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('/sso/start', 'GET', [], [], [], [
        'HTTP_X_SSO_TENANT' => $tenant->ulid,
    ]);

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});

it('returns null when header missing', function () {
    $resolver = new HeaderTenantResolver(
        enabled: true,
        headerName: 'X-SSO-Tenant',
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('/sso/start', 'GET');

    expect($resolver->resolve($request))->toBeNull();
});

it('throws misconfigured exception when enabled but header name empty', function () {
    $resolver = new HeaderTenantResolver(
        enabled: true,
        headerName: '',
        tenants: new EloquentTenantRepository(),
    );

    $request = Request::create('/sso/start', 'GET');

    expect(fn () => $resolver->resolve($request))
        ->toThrow(TenantResolutionFailed::class);
});
