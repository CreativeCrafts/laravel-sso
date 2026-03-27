<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\GuardSelector;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Policies\AllowIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\AllowProvisioningPolicy;
use CreativeCrafts\LaravelSso\Policies\DefaultIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\DefaultProvisioningPolicy;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Str;

uses(TestCase::class);

it('selects guard from connection with fallback', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid()]);
    $selector = app(GuardSelector::class);

    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.defaults.guard', 'web');

    $conn = new Connection(['guard' => 'admin']);
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    expect($selector->selectGuard($tenant, $conn))->toBe('admin');

    $conn = new Connection(['guard' => null]);
    expect($selector->selectGuard($tenant, $conn))->toBe('web');
});

it('default provisioning and linking policies deny by default', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid()]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Example',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = new Connection(['settings' => []]);
    $claims = ['email' => 'a@b.test'];
    $user = new AuthenticatableUser();

    $provisioning = app(DefaultProvisioningPolicy::class);
    expect($provisioning->allows($tenant, $connection, $identityProvider, $claims))->toBeFalse();

    $linking = app(DefaultIdentityLinkPolicy::class);
    expect($linking->allows($tenant, $connection, $identityProvider, $user, $claims))->toBeFalse();
});

it('connection settings override deny-by-default provisioning and linking policies', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid()]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Example',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = new Connection([
        'settings' => [
            'allow_provisioning' => true,
            'allow_identity_linking' => true,
        ],
    ]);

    $claims = ['email' => 'a@b.test'];
    $user = new AuthenticatableUser();

    $provisioning = app(DefaultProvisioningPolicy::class);
    expect($provisioning->allows($tenant, $connection, $identityProvider, $claims))->toBeTrue();

    $linking = app(DefaultIdentityLinkPolicy::class);
    expect($linking->allows($tenant, $connection, $identityProvider, $user, $claims))->toBeTrue();
});

it('connection settings take precedence over package-wide allow defaults', function () {
    config()->set('sso.provisioning.enabled_by_default', true);
    config()->set('sso.linking.enabled_by_default', true);

    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid()]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Example',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = new Connection([
        'settings' => [
            'allow_provisioning' => false,
            'allow_identity_linking' => false,
        ],
    ]);

    $claims = ['email' => 'a@b.test'];
    $user = new AuthenticatableUser();

    $provisioning = app(DefaultProvisioningPolicy::class);
    expect($provisioning->allows($tenant, $connection, $identityProvider, $claims))->toBeFalse();

    $linking = app(DefaultIdentityLinkPolicy::class);
    expect($linking->allows($tenant, $connection, $identityProvider, $user, $claims))->toBeFalse();
});

it('treats null connection settings as disabled flags', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid()]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Example',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = new Connection([
        'settings' => null,
    ]);

    $claims = ['email' => 'a@b.test'];
    $user = new AuthenticatableUser();

    $provisioning = app(DefaultProvisioningPolicy::class);
    expect($provisioning->allows($tenant, $connection, $identityProvider, $claims))->toBeFalse();

    $linking = app(DefaultIdentityLinkPolicy::class);
    expect($linking->allows($tenant, $connection, $identityProvider, $user, $claims))->toBeFalse();
});

it('explicit allow policies remain available for host applications', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid()]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Example',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = new Connection();
    $claims = ['email' => 'a@b.test'];
    $user = new AuthenticatableUser();

    $provisioning = new AllowProvisioningPolicy();
    expect($provisioning->allows($tenant, $connection, $identityProvider, $claims))->toBeTrue();

    $linking = new AllowIdentityLinkPolicy();
    expect($linking->allows($tenant, $connection, $identityProvider, $user, $claims))->toBeTrue();
});
