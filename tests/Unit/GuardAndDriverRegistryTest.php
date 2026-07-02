<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\ConfigDriverRegistry;
use CreativeCrafts\LaravelSso\Core\DefaultGuardSelector;
use CreativeCrafts\LaravelSso\Exceptions\GuardSelectionFailed;
use CreativeCrafts\LaravelSso\Exceptions\UnsupportedSsoProtocol;
use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;
use Illuminate\Support\Facades\Config;

uses(SsoTestHelpers::class);

it('selects connection guard or configured defaults', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);
    $connection->guard = 'web';
    $connection->save();

    Config::set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);

    expect((new DefaultGuardSelector(app('config')))->selectGuard($tenant, $connection->fresh()))->toBe('web');
});

it('falls back to sso and auth default guards', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    Config::set('sso.guards.default', 'api');
    Config::set('auth.guards.api', ['driver' => 'token', 'provider' => 'users']);

    expect((new DefaultGuardSelector(app('config')))->selectGuard($tenant, $connection))->toBe('api');
});

it('rejects disallowed or unconfigured guards', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);
    $connection->guard = 'admin';
    $connection->save();

    Config::set('sso.guards.allowed', ['web', '', 123, 'api']);
    Config::set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);

    (new DefaultGuardSelector(app('config')))->selectGuard($tenant, $connection->fresh());
})->throws(GuardSelectionFailed::class);

it('throws when the selected guard is not configured', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);
    $connection->guard = 'missing';
    $connection->save();

    (new DefaultGuardSelector(app('config')))->selectGuard($tenant, $connection->fresh());
})->throws(GuardSelectionFailed::class);

it('resolves configured protocol drivers from the container', function (): void {
    Config::set('sso.drivers', [
        'oidc' => CreativeCrafts\LaravelSso\Drivers\OidcDriver::class,
    ]);

    $driver = (new ConfigDriverRegistry(app()))->get('oidc');

    expect($driver)->toBeInstanceOf(CreativeCrafts\LaravelSso\Drivers\OidcDriver::class);
});

it('throws for unknown or invalid driver bindings', function (): void {
    Config::set('sso.drivers', 'invalid');

    (new ConfigDriverRegistry(app()))->get('missing');
})->throws(UnsupportedSsoProtocol::class);

it('throws when a configured driver class does not implement the driver contract', function (): void {
    Config::set('sso.drivers', [
        'bad' => stdClass::class,
    ]);

    (new ConfigDriverRegistry(app()))->get('bad');
})->throws(UnsupportedSsoProtocol::class);
