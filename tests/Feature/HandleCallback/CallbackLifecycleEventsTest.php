<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Events\CallbackFailed;
use CreativeCrafts\LaravelSso\Events\CallbackSucceeded;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptValidationInProgress;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(TestCase::class);

it('dispatches callback succeeded with the expected payload', function () {
    Event::fake([
        CallbackSucceeded::class,
    ]);

    config()->set('sso.drivers', [
        'oidc' => FakeOidcCallbackDriver::class,
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
        'settings' => [],
    ]);

    $attempts = app(AuthAttemptService::class);

    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
        redirectTo: '/after',
    );

    $request = Request::create('/sso/callback', 'GET', ['state' => $attempt->state]);

    $result = app(HandleCallback::class)->handle($request, $tenant, $connection->id);

    expect($result->authenticated)->toBeTrue();

    Event::assertDispatchedTimes(CallbackSucceeded::class, 1);
    Event::assertDispatched(CallbackSucceeded::class, function (CallbackSucceeded $event) use ($tenant, $connection, $idp, $attempt): bool {
        return $event->tenant->is($tenant)
            && $event->connection->is($connection)
            && $event->identityProvider->is($idp)
            && $event->attempt->is($attempt)
            && $event->callback->authenticated === true;
    });
});

it('dispatches callback failed when callback handling throws', function () {
    Event::fake([
        CallbackFailed::class,
    ]);

    config()->set('sso.drivers', [
        'oidc' => FakeOidcCallbackDriver::class,
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
        'settings' => [],
    ]);

    $attempts = app(AuthAttemptService::class);

    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
    );

    $request = Request::create('/sso/callback', 'GET', ['state' => $attempt->state]);

    $service = app(HandleCallback::class);

    $service->handle($request, $tenant, $connection->id);

    expect(fn () => $service->handle($request, $tenant, $connection->id))
        ->toThrow(AuthAttemptValidationInProgress::class);

    Event::assertDispatchedTimes(CallbackFailed::class, 1);
    Event::assertDispatched(CallbackFailed::class, function (CallbackFailed $event) use ($tenant, $connection): bool {
        return $event->tenant->is($tenant)
            && $event->connectionId === $connection->id
            && $event->exceptionClass === AuthAttemptValidationInProgress::class
            && $event->attempt === null
            && $event->connection?->is($connection) === true
            && $event->identityProvider !== null
            && $event->protocol === null;
    });
});
