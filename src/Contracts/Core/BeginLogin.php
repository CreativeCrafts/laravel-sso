<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

interface BeginLogin
{
    public function handle(Request $request, Tenant $tenant, int $connectionId): DriverStartResult;
}
