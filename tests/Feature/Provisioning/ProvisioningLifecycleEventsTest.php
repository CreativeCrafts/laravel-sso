<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Events\IdentityLinked;
use CreativeCrafts\LaravelSso\Events\LoginCompleted;
use CreativeCrafts\LaravelSso\Events\UserProvisioned;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

it('dispatches provisioning lifecycle events in order for a newly provisioned user', function () {
    $sequence = [];

    Event::listen(UserProvisioned::class, static function () use (&$sequence): void {
        $sequence[] = UserProvisioned::class;
    });

    Event::listen(IdentityLinked::class, static function () use (&$sequence): void {
        $sequence[] = IdentityLinked::class;
    });

    Event::listen(LoginCompleted::class, static function () use (&$sequence): void {
        $sequence[] = LoginCompleted::class;
    });

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

    $user = app(ProvisionAndLink::class)->handle($request, $tenant, $connection->id, $callback);

    expect($user)->toBeInstanceOf(User::class)
        ->and($sequence)->toBe([
            UserProvisioned::class,
            IdentityLinked::class,
            LoginCompleted::class,
        ]);
});

it('dispatches link and login events without provisioning when linking an existing user', function () {
    Event::fake([
        UserProvisioned::class,
        IdentityLinked::class,
        LoginCompleted::class,
    ]);

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

    $user = app(ProvisionAndLink::class)->handle($request, $tenant, $connection->id, $callback);

    expect($user->getAuthIdentifier())->toBe($existing->getAuthIdentifier());

    Event::assertNotDispatched(UserProvisioned::class);
    Event::assertDispatchedTimes(IdentityLinked::class, 1);
    Event::assertDispatchedTimes(LoginCompleted::class, 1);
    Event::assertDispatched(IdentityLinked::class, fn (IdentityLinked $event): bool => $event->user->getAuthIdentifier() === $existing->getAuthIdentifier());
    Event::assertDispatched(LoginCompleted::class, fn (LoginCompleted $event): bool => $event->guard === 'web');
});
