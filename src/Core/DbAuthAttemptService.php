<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use Carbon\CarbonInterface;
use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptExpired;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptNotFound;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptValidationInProgress;
use CreativeCrafts\LaravelSso\Exceptions\InvalidAuthAttemptBinding;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class DbAuthAttemptService implements AuthAttemptService
{
    /**
     * @param array<string, mixed> $context
     */
    public function create(
        Tenant $tenant,
        string $protocol,
        ?Connection $connection = null,
        ?IdentityProvider $identityProvider = null,
        ?string $redirectTo = null,
        ?string $codeVerifier = null,
        bool $withNonce = true,
        array $context = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): AuthAttempt {
        $ttlSeconds = ConfigHelper::positiveInt('sso.attempts.ttl_seconds', 600);
        $stateLength = ConfigHelper::positiveInt('sso.attempts.state_length', 64);
        $nonceLength = ConfigHelper::positiveInt('sso.attempts.nonce_length', 64);

        return AuthAttempt::query()->create([
          'tenant_id' => $tenant->id,
          'connection_id' => $connection?->id,
          'identity_provider_id' => $identityProvider?->id,
          'protocol' => $protocol,
          'state' => Str::random($stateLength),
          'nonce' => $withNonce ? Str::random($nonceLength) : null,
          'code_verifier' => $codeVerifier,
          'redirect_to' => $redirectTo,
          'expires_at' => now()->addSeconds($ttlSeconds),
          'consumed_at' => null,
          'status' => AuthAttempt::STATUS_PENDING,
          'validating_at' => null,
          'failed_at' => null,
          'ip' => $this->boundedString($ip, 255),
          'user_agent' => $this->boundedString($userAgent, 1024),
          'context' => $context,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function reserveForValidation(
        Tenant $tenant,
        string $state,
        ?int $expectedConnectionId = null,
        ?int $expectedIdentityProviderId = null,
    ): AuthAttempt {
        return DB::transaction(function () use ($tenant, $state, $expectedConnectionId, $expectedIdentityProviderId): AuthAttempt {
            $attempt = $this->lockedAttempt($tenant, $state);

            $this->assertAttemptUsable($attempt, $state, $expectedConnectionId, $expectedIdentityProviderId);

            $now = now();

            if ($attempt->isValidating() && $this->hasFreshValidationLock($attempt, $now)) {
                throw AuthAttemptValidationInProgress::forState($state);
            }

            $attempt->forceFill([
              'status' => AuthAttempt::STATUS_VALIDATING,
              'validating_at' => $now,
            ])->save();

            return $attempt->refresh();
        });
    }

    /**
     * @throws Throwable
     */
    public function markConsumed(AuthAttempt $attempt): AuthAttempt
    {
        return DB::transaction(static function () use ($attempt): AuthAttempt {
            /** @var AuthAttempt|null $locked */
            $locked = AuthAttempt::query()
              ->whereKey($attempt->id)
              ->lockForUpdate()
              ->first();

            if (!$locked instanceof AuthAttempt) {
                throw AuthAttemptNotFound::forState($attempt->state);
            }

            if ($locked->isConsumed()) {
                throw AuthAttemptAlreadyConsumed::forState($locked->state);
            }

            $locked->forceFill([
              'status' => AuthAttempt::STATUS_CONSUMED,
              'consumed_at' => now(),
              'validating_at' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @throws Throwable
     */
    public function markValidationFailed(AuthAttempt $attempt): AuthAttempt
    {
        return DB::transaction(static function () use ($attempt): AuthAttempt {
            /** @var AuthAttempt|null $locked */
            $locked = AuthAttempt::query()
              ->whereKey($attempt->id)
              ->lockForUpdate()
              ->first();

            if (!$locked instanceof AuthAttempt) {
                throw AuthAttemptNotFound::forState($attempt->state);
            }

            if ($locked->isConsumed()) {
                return $locked->refresh();
            }

            $locked->forceFill([
              'status' => AuthAttempt::STATUS_PENDING,
              'validating_at' => null,
              'failed_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @throws Throwable
     */
    public function consumeByState(
        Tenant $tenant,
        string $state,
        ?int $expectedConnectionId = null,
        ?int $expectedIdentityProviderId = null,
    ): AuthAttempt {
        $attempt = $this->reserveForValidation(
            tenant: $tenant,
            state: $state,
            expectedConnectionId: $expectedConnectionId,
            expectedIdentityProviderId: $expectedIdentityProviderId,
        );

        return $this->markConsumed($attempt);
    }

    private function lockedAttempt(Tenant $tenant, string $state): AuthAttempt
    {
        /** @var AuthAttempt|null $attempt */
        $attempt = AuthAttempt::query()
          ->where('tenant_id', $tenant->id)
          ->where('state', $state)
          ->lockForUpdate()
          ->first();

        if (!$attempt instanceof AuthAttempt) {
            throw AuthAttemptNotFound::forState($state);
        }

        return $attempt;
    }

    private function assertAttemptUsable(
        AuthAttempt $attempt,
        string $state,
        ?int $expectedConnectionId,
        ?int $expectedIdentityProviderId,
    ): void {
        if ($attempt->isConsumed()) {
            throw AuthAttemptAlreadyConsumed::forState($state);
        }

        if ($attempt->isExpired(now())) {
            throw AuthAttemptExpired::forState($state);
        }

        if ($expectedConnectionId !== null && $attempt->connection_id !== null && (int) $attempt->connection_id !== $expectedConnectionId) {
            throw InvalidAuthAttemptBinding::connectionMismatch(
                expected: (int) $attempt->connection_id,
                actual: $expectedConnectionId,
            );
        }

        if ($expectedIdentityProviderId !== null && $attempt->identity_provider_id !== null && (int) $attempt->identity_provider_id !== $expectedIdentityProviderId) {
            throw InvalidAuthAttemptBinding::identityProviderMismatch(
                expected: (int) $attempt->identity_provider_id,
                actual: $expectedIdentityProviderId,
            );
        }
    }

    private function hasFreshValidationLock(AuthAttempt $attempt, CarbonInterface $now): bool
    {
        if ($attempt->validating_at === null) {
            return false;
        }

        return $attempt->validating_at
            ->copy()
            ->addSeconds($this->validationLockTtlSeconds())
            ->greaterThan($now);
    }

    private function validationLockTtlSeconds(): int
    {
        return ConfigHelper::positiveInt('sso.attempts.validation_lock_ttl_seconds', 120);
    }

    private function boundedString(?string $value, int $maxLength): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
