<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

interface TenantResolver
{
    public function resolve(Request $request): ?Tenant;
}
