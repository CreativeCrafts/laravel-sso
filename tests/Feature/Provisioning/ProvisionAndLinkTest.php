<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
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

it('provisions a new user, links external identity, and logs in', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $canonical = new Claims(
        subject: 'sub-123',
        email: 'user@example.test',
        displayName: 'User One',
        emailVerified: null,
        groups: [],
        normalized: [
        'sub' => 'sub-123',
        'email' => 'user@example.test',
        'name' => 'User One',
        'email_verified' => null,
        'groups' => [],
      ],
    );

    $callback = new DriverCallbackResult(
        authenticated: true,
        canonicalClaims: $canonical,
        subject: $canonical->subject,
        email: $canonical->email,
        displayName: $canonical->displayName,
        claims: $canonical->toArray(),
    );

    $request = Request::create('/sso/callback', 'GET');

    $svc = app(ProvisionAndLink::class);

    $user = $svc->handle($request, $tenant, $connection->id, $callback);

    expect($user)
      ->toBeInstanceOf(User::class)
      ->and($user->email)->toBe('user@example.test');

    $external = ExternalIdentity::query()
      ->where('tenant_id', $tenant->id)
      ->where('identity_provider_id', $idp->id)
      ->where('provider_subject', 'sub-123')
      ->first();

    expect($external)->not
      ->toBeNull()
      ->and($external?->authenticatable_type)->toBe(User::class);

    expect(auth('web')->check())->toBeTrue();
});

it('links an existing user by email and logs in', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $existing = User::query()->create([
      'email' => 'user@example.test',
      'name' => 'Existing',
    ]);

    $canonical = new Claims(
        subject: 'sub-456',
        email: 'user@example.test',
        displayName: 'Existing',
        emailVerified: null,
        groups: [],
        normalized: [
        'sub' => 'sub-456',
        'email' => 'user@example.test',
        'name' => 'Existing',
        'email_verified' => null,
        'groups' => [],
      ],
    );

    $callback = new DriverCallbackResult(
        authenticated: true,
        canonicalClaims: $canonical,
        subject: $canonical->subject,
        email: $canonical->email,
        displayName: $canonical->displayName,
        claims: $canonical->toArray(),
    );

    $request = Request::create('/sso/callback', 'GET');

    $svc = app(ProvisionAndLink::class);

    $user = $svc->handle($request, $tenant, $connection->id, $callback);

    expect($user->getAuthIdentifier())->toBe($existing->getAuthIdentifier());

    $external = ExternalIdentity::query()
      ->where('tenant_id', $tenant->id)
      ->where('identity_provider_id', $idp->id)
      ->where('provider_subject', 'sub-456')
      ->first();

    expect($external)->not
      ->toBeNull()
      ->and($external?->authenticatable_id)->toBe((string)$existing->getAuthIdentifier());

    expect(auth('web')->check())->toBeTrue();
});
