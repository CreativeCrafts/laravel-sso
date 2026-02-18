<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\GuardSelector;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Facades\Config;

final class DefaultGuardSelector implements GuardSelector
{
    public function selectGuard(Tenant $tenant, Connection $connection): string
    {
        if (is_string($connection->guard) && $connection->guard !== '') {
            return $connection->guard;
        }

        return Config::string(key: 'auth.defaults.guard', default: 'web');
    }
}
