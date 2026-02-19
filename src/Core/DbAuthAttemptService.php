<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptExpired;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptNotFound;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Facades\Config;
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
    ): AuthAttempt {
        $ttlSeconds = Config::integer(key: 'sso.attempts.ttl_seconds', default: 600);
        $stateLength = Config::integer(key: 'sso.attempts.state_length', default: 64);
        $nonceLength = Config::integer(key: 'sso.attempts.nonce_length', default: 64);

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
          'ip' => null,
          'user_agent' => null,
          'context' => $context,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function consumeByState(Tenant $tenant, string $state): AuthAttempt
    {
        return DB::transaction(static function () use ($tenant, $state): AuthAttempt {
            $attempt = AuthAttempt::query()
              ->where('tenant_id', $tenant->id)
              ->where('state', $state)
              ->lockForUpdate()
              ->first();

            if ($attempt === null) {
                throw AuthAttemptNotFound::forState($state);
            }

            if ($attempt->isConsumed()) {
                throw AuthAttemptAlreadyConsumed::forState($state);
            }

            if ($attempt->isExpired(now())) {
                throw AuthAttemptExpired::forState($state);
            }

            $attempt->forceFill([
              'consumed_at' => now(),
            ])->save();

            return $attempt->refresh();
        });
    }
}
