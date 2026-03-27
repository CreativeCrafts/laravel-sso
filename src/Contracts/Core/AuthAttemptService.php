<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;

interface AuthAttemptService
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
    ): AuthAttempt;

    /**
     * Consume by state within the tenant scope (replay-safe). Returns the consumed attempt.
     */
    public function consumeByState(
        Tenant $tenant,
        string $state,
        ?int $expectedConnectionId = null,
        ?int $expectedIdentityProviderId = null,
    ): AuthAttempt;
}
