<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;

it('records retryable validation failures as pending with failed_at', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => '01J00000000000000000000001',
        'name' => 'Tenant One',
        'metadata' => [],
    ]);

    /** @var AuthAttemptService $attempts */
    $attempts = app(AuthAttemptService::class);

    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        withNonce: true,
    );

    $reserved = $attempts->reserveForValidation($tenant, $attempt->state);
    $failed = $attempts->markValidationFailed($reserved);

    expect($failed->status)->toBe(AuthAttempt::STATUS_PENDING)
        ->and($failed->failed_at)->not->toBeNull()
        ->and($failed->validating_at)->toBeNull()
        ->and($failed->hasRetryableValidationFailure())->toBeTrue();
});

it('allows retryable failed attempts to be reserved and consumed later', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => '01J00000000000000000000002',
        'name' => 'Tenant Two',
        'metadata' => [],
    ]);

    /** @var AuthAttemptService $attempts */
    $attempts = app(AuthAttemptService::class);

    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        withNonce: true,
    );

    $reserved = $attempts->reserveForValidation($tenant, $attempt->state);
    $failed = $attempts->markValidationFailed($reserved);

    $retried = $attempts->reserveForValidation($tenant, $failed->state);
    $consumed = $attempts->markConsumed($retried);

    expect($consumed->status)->toBe(AuthAttempt::STATUS_CONSUMED)
        ->and($consumed->consumed_at)->not->toBeNull()
        ->and($consumed->failed_at)->not->toBeNull()
        ->and($consumed->hasRetryableValidationFailure())->toBeFalse();
});

it('documents consumeByState as terminal and replay safe', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => '01J00000000000000000000003',
        'name' => 'Tenant Three',
        'metadata' => [],
    ]);

    /** @var AuthAttemptService $attempts */
    $attempts = app(AuthAttemptService::class);

    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        withNonce: true,
    );

    $consumed = $attempts->consumeByState($tenant, $attempt->state);

    expect($consumed->status)->toBe(AuthAttempt::STATUS_CONSUMED)
        ->and($consumed->consumed_at)->not->toBeNull();

    expect(fn () => $attempts->consumeByState($tenant, $attempt->state))
        ->toThrow(AuthAttemptAlreadyConsumed::class);
});
