<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;

/**
 * Eloquent-based implementation of the AuthAttemptRepository.
 * This repository provides read-only access to auth attempts and does not
 * perform any state transitions such as consumption or expiration. Those
 * operations remain the responsibility of AuthAttemptService.
 */
final class EloquentAuthAttemptRepository implements AuthAttemptRepository
{
    public function findByState(Tenant $tenant, string $state): ?AuthAttempt
    {
        return AuthAttempt::query()
          ->where('tenant_id', $tenant->id)
          ->where('state', $state)
          ->first();
    }
}
