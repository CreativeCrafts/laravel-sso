<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    config()->set('sso.drivers.oidc', FakeOidcCallbackDriver::class);

    if (!Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);
});

it('oidc callback endpoint provisions user and redirects when provisioning is enabled for the connection', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'T1',
    ]);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Default',
        'enabled' => true,
        'settings' => ['allow_provisioning' => true],
    ]);

    $redirectUrl = route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'idp' => (string) $connection->id,
    ]);

    $redirectResponse = $this->get($redirectUrl);
    $redirectResponse->assertStatus(302);

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;
    expect($state)->toBeString()->not->toBeEmpty();

    $callbackUrl = route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'idp' => (string) $connection->id,
    ]);

    $response = $this->get($callbackUrl . '?state=' . urlencode((string) $state));

    $response->assertRedirect('/');
    expect(auth('web')->check())->toBeTrue();
});

it('oidc callback endpoint fails when provisioning is denied by default', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'T1',
    ]);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Default',
        'enabled' => true,
        'settings' => [],
    ]);

    $redirectUrl = route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'idp' => (string) $connection->id,
    ]);

    $redirectResponse = $this->get($redirectUrl);
    $redirectResponse->assertStatus(302);

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;
    expect($state)->toBeString()->not->toBeEmpty();

    $callbackUrl = route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'idp' => (string) $connection->id,
    ]);

    $response = $this->get($callbackUrl . '?state=' . urlencode((string) $state));

    $response->assertStatus(500);
    expect(auth('web')->check())->toBeFalse();
});
