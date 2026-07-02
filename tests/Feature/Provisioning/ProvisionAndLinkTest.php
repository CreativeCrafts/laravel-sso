<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\UserLocator;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Exceptions\IdentityLinkDenied;
use CreativeCrafts\LaravelSso\Exceptions\ProvisioningDenied;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
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

it('denies provisioning by default', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

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
    $service = app(ProvisionAndLink::class);

    expect(fn () => $service->handle($request, $tenant, $connection->id, $callback))
        ->toThrow(ProvisioningDenied::class);
});

it('provisions a new user, links external identity, and logs in when connection provisioning is enabled', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

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
        'settings' => ['allow_provisioning' => true],
    ]);

    $canonical = new Claims(
        subject: 'sub-123',
        email: 'user@example.test',
        displayName: 'User One',
        emailVerified: true,
        groups: [],
        normalized: [
            'sub' => 'sub-123',
            'email' => 'user@example.test',
            'name' => 'User One',
            'email_verified' => true,
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
    $service = app(ProvisionAndLink::class);

    $user = $service->handle($request, $tenant, $connection->id, $callback);

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

it('denies linking an existing user by email by default', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

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

    User::query()->create([
        'email' => 'user@example.test',
        'name' => 'Existing',
    ]);

    $canonical = new Claims(
        subject: 'sub-456',
        email: 'user@example.test',
        displayName: 'Existing',
        emailVerified: true,
        groups: [],
        normalized: [
            'sub' => 'sub-456',
            'email' => 'user@example.test',
            'name' => 'Existing',
            'email_verified' => true,
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
    $service = app(ProvisionAndLink::class);

    expect(fn () => $service->handle($request, $tenant, $connection->id, $callback))
        ->toThrow(IdentityLinkDenied::class);
});

it('denies linking via the provisioning race path when identity linking is disabled', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

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
        'settings' => ['allow_provisioning' => true],
    ]);

    User::query()->create([
        'email' => 'victim@example.test',
        'name' => 'Victim',
    ]);

    $realLocator = app(UserLocator::class);

    app()->instance(UserLocator::class, new class ($realLocator) implements UserLocator {
        private bool $hideExistingUserOnce = true;

        public function __construct(
            private readonly UserLocator $delegate,
        ) {
        }

        public function findByEmail(string $guard, string $email): ?Authenticatable
        {
            if ($this->hideExistingUserOnce) {
                $this->hideExistingUserOnce = false;

                return null;
            }

            return $this->delegate->findByEmail($guard, $email);
        }
    });

    $canonical = new Claims(
        subject: 'attacker-subject',
        email: 'victim@example.test',
        displayName: 'Attacker',
        emailVerified: true,
        groups: [],
        normalized: [
            'sub' => 'attacker-subject',
            'email' => 'victim@example.test',
            'name' => 'Attacker',
            'email_verified' => true,
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
    $service = app(ProvisionAndLink::class);

    expect(fn () => $service->handle($request, $tenant, $connection->id, $callback))
        ->toThrow(IdentityLinkDenied::class);

    expect(ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('provider_subject', 'attacker-subject')
        ->exists())->toBeFalse();
});

it('links an existing user by email and logs in when connection identity linking is enabled', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

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
        'settings' => ['allow_identity_linking' => true],
    ]);

    $existing = User::query()->create([
        'email' => 'user@example.test',
        'name' => 'Existing',
    ]);

    $canonical = new Claims(
        subject: 'sub-456',
        email: 'user@example.test',
        displayName: 'Existing',
        emailVerified: true,
        groups: [],
        normalized: [
            'sub' => 'sub-456',
            'email' => 'user@example.test',
            'name' => 'Existing',
            'email_verified' => true,
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
    $service = app(ProvisionAndLink::class);

    $user = $service->handle($request, $tenant, $connection->id, $callback);

    expect($user->getAuthIdentifier())->toBe($existing->getAuthIdentifier());

    $external = ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('identity_provider_id', $idp->id)
        ->where('provider_subject', 'sub-456')
        ->first();

    expect($external)->not
        ->toBeNull()
        ->and($external?->authenticatable_id)->toBe((string) $existing->getAuthIdentifier());

    expect(auth('web')->check())->toBeTrue();
});

it('package-wide config can enable provisioning and linking when connection settings are absent', function () {
    config()->set('sso.provisioning.enabled_by_default', true);
    config()->set('sso.linking.enabled_by_default', true);

    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

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
        subject: 'sub-789',
        email: 'package@example.test',
        displayName: 'Package Enabled',
        emailVerified: true,
        groups: [],
        normalized: [
            'sub' => 'sub-789',
            'email' => 'package@example.test',
            'name' => 'Package Enabled',
            'email_verified' => true,
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
    $service = app(ProvisionAndLink::class);

    $user = $service->handle($request, $tenant, $connection->id, $callback);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('package@example.test');
});
