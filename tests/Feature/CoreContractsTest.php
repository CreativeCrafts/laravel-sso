<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\DefaultGuardSelector;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Policies\AllowIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\AllowProvisioningPolicy;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
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
    $connection = new Connection();

    $provisioning = new AllowProvisioningPolicy();
    expect($provisioning->shouldProvision($tenant, $connection, ['email' => 'a@b.test']))->toBeTrue();

    $linking = new AllowIdentityLinkPolicy();
    $user = new class () extends Model {};
    expect($linking->shouldLinkToUser($tenant, $connection, $user, ['email' => 'a@b.test']))->toBeTrue();
});
