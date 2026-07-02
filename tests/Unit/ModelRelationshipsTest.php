<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\AuditLog;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(SsoTestHelpers::class);

it('exposes model relationships and auth attempt helpers', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    $attempt = AuthAttempt::query()->create([
        'tenant_id' => $tenant->id,
        'connection_id' => $connection->id,
        'identity_provider_id' => $idp->id,
        'protocol' => 'oidc',
        'state' => 'state-' . Str::random(8),
        'expires_at' => now()->addMinutes(10),
        'status' => AuthAttempt::STATUS_PENDING,
        'context' => [],
    ]);

    $audit = AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'connection_id' => $connection->id,
        'auth_attempt_id' => $attempt->id,
        'event' => 'test.event',
        'level' => 'info',
        'context' => ['ok' => true],
    ]);

    $external = ExternalIdentity::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'provider_subject' => 'subject-1',
        'email' => 'user@example.test',
        'display_name' => 'User',
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => 1,
        'claims' => ['email' => 'user@example.test'],
    ]);

    expect($connection->fresh()->tenant?->is($tenant))->toBeTrue()
        ->and($connection->identityProvider?->is($idp))->toBeTrue()
        ->and($connection->authAttempts()->count())->toBe(1)
        ->and($connection->auditLogs()->count())->toBe(1)
        ->and($idp->fresh()->connections()->count())->toBe(1)
        ->and($idp->externalIdentities()->count())->toBe(1)
        ->and($idp->authAttempts()->count())->toBe(1)
        ->and($idp->auditLogs()->count())->toBe(1)
        ->and($attempt->tenant?->is($tenant))->toBeTrue()
        ->and($attempt->connection?->is($connection))->toBeTrue()
        ->and($attempt->identityProvider?->is($idp))->toBeTrue()
        ->and($attempt->auditLogs()->count())->toBe(1)
        ->and($audit->tenant?->is($tenant))->toBeTrue()
        ->and($audit->connection?->is($connection))->toBeTrue()
        ->and($audit->identityProvider?->is($idp))->toBeTrue()
        ->and($audit->authAttempt?->is($attempt))->toBeTrue()
        ->and($external->tenant?->is($tenant))->toBeTrue()
        ->and($external->identityProvider?->is($idp))->toBeTrue();

    $now = Carbon::parse('2026-01-01 00:00:00');
    $attempt->expires_at = $now;
    expect($attempt->isExpired($now))->toBeTrue()
        ->and($attempt->isConsumed())->toBeFalse()
        ->and($attempt->isValidating())->toBeFalse();

    $attempt->failed_at = $now;
    expect($attempt->hasRetryableValidationFailure())->toBeTrue();

    $attempt->consumed_at = $now;
    $attempt->status = AuthAttempt::STATUS_CONSUMED;
    expect($attempt->isConsumed())->toBeTrue()
        ->and($attempt->hasRetryableValidationFailure())->toBeFalse();
});

it('auto assigns connection ulids on create', function (): void {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => ['client_id' => 'x', 'redirect_uri' => 'https://app.test/callback', 'issuer' => 'https://idp.test'],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Conn',
        'enabled' => true,
        'settings' => [],
    ]);

    expect($connection->ulid)->not->toBeNull();
});

it('auto assigns identity provider ulids on create', function (): void {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => ['client_id' => 'x', 'redirect_uri' => 'https://app.test/callback', 'issuer' => 'https://idp.test'],
    ]);

    expect($idp->ulid)->not->toBeNull();
});
