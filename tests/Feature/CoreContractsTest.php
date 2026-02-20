<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\DefaultGuardSelector;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Policies\AllowIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\AllowProvisioningPolicy;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Str;

uses(TestCase::class);

it('selects guard from connection with fallback', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid()]);
    $selector = new DefaultGuardSelector();

    $conn = new Connection(['guard' => 'admin']);
    expect($selector->selectGuard($tenant, $conn))->toBe('admin');

    $conn = new Connection(['guard' => null]);
    config()?->set('auth.defaults.guard', 'web');
    expect($selector->selectGuard($tenant, $conn))->toBe('web');
});

it('default policies allow provisioning and linking', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid()]);

    $identityProvider = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Example',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connection = new Connection();

    $claims = ['email' => 'a@b.test'];

    $provisioning = new AllowProvisioningPolicy();
    expect($provisioning->allows($tenant, $connection, $identityProvider, $claims))->toBeTrue();

    $linking = new AllowIdentityLinkPolicy();
    $user = new AuthenticatableUser();
    expect($linking->allows($tenant, $connection, $identityProvider, $user, $claims))->toBeTrue();
});
