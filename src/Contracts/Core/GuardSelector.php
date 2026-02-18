<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;

interface GuardSelector
{
    public function selectGuard(Tenant $tenant, Connection $connection): string;
}
