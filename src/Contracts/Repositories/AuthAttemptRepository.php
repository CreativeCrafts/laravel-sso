<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;

/**
 * Provides read-only access to authentication attempts scoped to a tenant.
 * Attempt consumption (marking an attempt as used or expired) is handled by
 * the AuthAttemptService. This repository should not implement any state
 * transitions or side effects.
 */
interface AuthAttemptRepository
{
    /**
     * Locate an authentication attempt by its state value within the given tenant.
     * Returns null if no matching attempt exists. Both consumed and unconsumed
     * attempts may be returned—callers are responsible for verifying the
     * consumed/expired state as needed.
     */
    public function findByState(Tenant $tenant, string $state): ?AuthAttempt;
}
