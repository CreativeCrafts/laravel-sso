<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptExpired;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(TestCase::class);

it('creates and consumes an auth attempt (single-use)', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);

    $service = app(AuthAttemptService::class);

    $attempt = $service->create(
        tenant: $tenant,
        protocol: 'oidc',
        redirectTo: '/after',
        codeVerifier: 'verifier',
        context: ['foo' => 'bar'],
    );

    expect($attempt->tenant_id)
      ->toBe($tenant->id)
      ->and($attempt->consumed_at)->toBeNull();

    $consumed = $service->consumeByState($tenant, $attempt->state);

    expect($consumed->id)
      ->toBe($attempt->id)
      ->and($consumed->consumed_at)->not->toBeNull();

    $fn = fn () => $service->consumeByState($tenant, $attempt->state);
    expect($fn)->toThrow(AuthAttemptAlreadyConsumed::class);
});

it('rejects expired attempts', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);
    $service = app(AuthAttemptService::class);

    config()?->set('sso.attempts.ttl_seconds', 1);

    $baseNow = now();
    Carbon::setTestNow($baseNow);

    try {
        $attempt = $service->create(tenant: $tenant, protocol: 'oidc', withNonce: false);

        Carbon::setTestNow($baseNow->copy()->addSeconds(2));

        $fn = fn () => $service->consumeByState($tenant, $attempt->state);
        expect($fn)->toThrow(AuthAttemptExpired::class);
    } finally {
        Carbon::setTestNow();
    }
});

it('does not allow cross-tenant consumption', function () {
    $tenantA = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);
    $tenantB = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'B']);

    $service = app(AuthAttemptService::class);

    $attempt = $service->create(tenant: $tenantA, protocol: 'oidc');

    $fn = fn () => $service->consumeByState($tenantB, $attempt->state);
    expect($fn)->toThrow(AuthAttemptNotFound::class);
});
