<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Models\Connection;
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

    config()->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);

    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.defaults.guard', 'web');

    config()->set('sso.guards.allowed', ['web', 'admin']);
});

it('logs in using the guard configured on the connection', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Okta',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $webConnection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Web',
        'enabled' => true,
        'guard' => 'web',
        'settings' => ['allow_provisioning' => true],
    ]);

    $adminConnection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Admin',
        'enabled' => true,
        'guard' => 'admin',
        'settings' => ['allow_provisioning' => true],
    ]);

    $service = app(ProvisionAndLink::class);
    $request = Request::create('/sso/callback', 'GET');

    $canonical = new Claims(
        subject: 'sub-guard',
        email: 'user@example.test',
        displayName: 'User One',
        emailVerified: null,
        groups: [],
        normalized: [
            'sub' => 'sub-guard',
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

    $service->handle($request, $tenant, $webConnection->id, $callback);

    expect(auth('web')->check())
        ->toBeTrue()
        ->and(auth('admin')->check())->toBeFalse();

    auth('web')->logout();

    $service->handle($request, $tenant, $adminConnection->id, $callback);

    expect(auth('admin')->check())
        ->toBeTrue()
        ->and(auth('web')->check())->toBeFalse();
});
