<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Policies;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

interface IdentityLinkPolicy
{
    /**
     * @param array<string, mixed> $claims
     */
    public function shouldLinkToUser(Tenant $tenant, Connection $connection, Model $user, array $claims): bool;
}
