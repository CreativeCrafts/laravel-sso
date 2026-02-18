<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

uses(TestCase::class);

it('resolves tenant by route param ulid', function () {
    config()?->set('sso.tenancy.route_param', 'tenant');
    config()?->set('sso.tenancy.default_tenant_ulid', null);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);

    $request = Request::create('/sso/' . $tenant->ulid . '/start', 'GET');

    $route = new Route(['GET'], '/sso/{tenant}/start', fn () => 'ok');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $resolver = app(TenantResolver::class);

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});

it('falls back to default tenant when no route param is available', function () {
    $default = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'Default']);

    config()?->set('sso.tenancy.route_param', 'tenant');
    config()?->set('sso.tenancy.default_tenant_ulid', $default->ulid);

    $request = Request::create('/sso/start', 'GET');
    $request->setRouteResolver(fn () => null);

    $resolver = app(TenantResolver::class);

    expect($resolver->resolve($request)?->id)->toBe($default->id);
});

it('returns null when no tenant can be resolved', function () {
    config()?->set('sso.tenancy.route_param', 'tenant');
    config()?->set('sso.tenancy.default_tenant_ulid', null);

    $request = Request::create('/sso/start', 'GET');
    $request->setRouteResolver(fn () => null);

    $resolver = app(TenantResolver::class);

    expect($resolver->resolve($request))->toBeNull();
});
