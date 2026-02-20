<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Models\AuditLog;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(TestCase::class);

it('handles callback, consumes attempt, and writes audit log', function () {
    config()?->set('sso.drivers', [
      'oidc' => FakeOidcCallbackDriver::class,
    ]);

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

    expect($audit)->not
      ->toBeNull()
      ->and($audit?->connection_id)->toBe($connection->id);
});

it('prevents replay by rejecting second consumption and writes failure audit', function () {
    config()?->set('sso.drivers', [
      'oidc' => FakeOidcCallbackDriver::class,
    ]);

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

    expect($failedAudit)->not->toBeNull();
});
