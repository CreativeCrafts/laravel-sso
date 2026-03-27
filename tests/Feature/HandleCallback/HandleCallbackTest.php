<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Exceptions\InvalidAuthAttemptBinding;
use CreativeCrafts\LaravelSso\Models\AuditLog;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(TestCase::class);

it('handles callback, consumes attempt, and writes a redacted audit log by default', function () {
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

    $useCase = app(HandleCallback::class);

    $result = $useCase->handle($request, $tenant, $connection->id);

    expect($result->authenticated)->toBeTrue();

    $audit = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event', 'sso.callback.succeeded')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->connection_id)->toBe($connection->id)
        ->and($audit?->context['protocol'])->toBe('oidc')
        ->and($audit?->context['status'])->toBe('succeeded')
        ->and($audit?->context['authenticated'])->toBeTrue()
        ->and($audit?->context['driver'])->toBe('fake')
        ->and($audit?->context['userinfo_used'])->toBeTrue()
        ->and($audit?->context)->not->toHaveKey('extended');

    $encoded = json_encode($audit?->context, JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('super-secret-access-token')
        ->not->toContain('header.payload.signature')
        ->not->toContain('user@example.test');
});

it('can include extended redacted audit detail when explicitly enabled', function () {
    config()->set('sso.audit.extended_context', true);
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

    $useCase = app(HandleCallback::class);
    $useCase->handle($request, $tenant, $connection->id);

    $audit = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event', 'sso.callback.succeeded')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->context)->toHaveKey('extended')
        ->and($audit?->context['extended']['driver_context']['access_token'])->toBe('[redacted]')
        ->and($audit?->context['extended']['driver_context']['id_token'])->toBe('[redacted]')
        ->and($audit?->context['extended']['claims']['email'])->toBe('u***@example.test');
});

it('prevents replay by rejecting second consumption and writes a redacted failure audit', function () {
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

    $useCase = app(HandleCallback::class);

    $request = Request::create('/sso/callback', 'GET', ['state' => $attempt->state]);

    $useCase->handle($request, $tenant, $connection->id);

    $fn = fn () => $useCase->handle($request, $tenant, $connection->id);

    expect($fn)->toThrow(AuthAttemptAlreadyConsumed::class);

    $failedAudit = AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('event', 'sso.callback.failed')
        ->first();

    expect($failedAudit)->not->toBeNull()
        ->and($failedAudit?->context['status'])->toBe('failed')
        ->and($failedAudit?->context['error_code'])->toBe('AuthAttemptAlreadyConsumed')
        ->and($failedAudit?->context)->not->toHaveKey('message')
        ->and($failedAudit?->context)->not->toHaveKey('state');

    $encoded = json_encode($failedAudit?->context, JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain($attempt->state);
});

it('does not consume auth attempts when connection binding mismatches', function () {
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

    $rightConnection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Default',
        'enabled' => true,
        'guard' => 'web',
        'settings' => [],
    ]);

    $wrongConnection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Other',
        'enabled' => true,
        'guard' => 'web',
        'settings' => [],
    ]);

    $attempt = app(AuthAttemptService::class)->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $rightConnection,
        identityProvider: $idp,
    );

    $request = Request::create('/sso/callback', 'GET', ['state' => $attempt->state]);

    expect(fn () => app(HandleCallback::class)->handle($request, $tenant, $wrongConnection->id))
        ->toThrow(InvalidAuthAttemptBinding::class);

    $attempt->refresh();
    expect($attempt->consumed_at)->toBeNull();
});
