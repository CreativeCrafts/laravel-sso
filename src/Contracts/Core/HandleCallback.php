<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

interface HandleCallback
{
    public function handle(Request $request, Tenant $tenant, int $connectionId): DriverCallbackResult;
}
