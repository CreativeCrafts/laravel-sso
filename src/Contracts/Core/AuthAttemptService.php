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
     * Create a short-lived protocol attempt in the pending state.
     *
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
    ): AuthAttempt;

    /**
     * Reserve by state within the tenant scope before protocol validation.
     *
     * This method is the preferred callback entry point. It moves a usable
     * pending attempt to the validating state under a database row lock.
     * The caller must later call markConsumed() after successful protocol
     * validation or markValidationFailed() after retryable validation failure.
     */
    public function reserveForValidation(
        Tenant $tenant,
        string $state,
        ?int $expectedConnectionId = null,
        ?int $expectedIdentityProviderId = null,
    ): AuthAttempt;

    /**
     * Mark a previously reserved attempt as consumed after successful protocol validation.
     *
     * Consumed attempts are terminal and must reject replay attempts.
     */
    public function markConsumed(AuthAttempt $attempt): AuthAttempt;

    /**
     * Release a previously reserved attempt after failed protocol validation.
     *
     * Validation failures are retryable while the attempt is unexpired and not
     * consumed. Implementations should set failed_at, clear validating_at, and
     * return the attempt to pending rather than using a terminal failed status.
     */
    public function markValidationFailed(AuthAttempt $attempt): AuthAttempt;

    /**
     * Consume by state within the tenant scope and return the consumed attempt.
     *
     * This legacy atomic helper reserves and immediately consumes the attempt.
     * It is replay-safe, but it is not suitable for protocol callbacks that
     * need to perform validation before consumption. Prefer reserveForValidation(),
     * followed by markConsumed() or markValidationFailed(), for OIDC/SAML flows.
     */
    public function consumeByState(
        Tenant $tenant,
        string $state,
        ?int $expectedConnectionId = null,
        ?int $expectedIdentityProviderId = null,
    ): AuthAttempt;
}
