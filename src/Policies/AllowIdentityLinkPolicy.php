<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

final class AllowIdentityLinkPolicy implements IdentityLinkPolicy
{
    public function shouldLinkToUser(Tenant $tenant, Connection $connection, Model $user, array $claims): bool
    {
        return true;
    }
}
