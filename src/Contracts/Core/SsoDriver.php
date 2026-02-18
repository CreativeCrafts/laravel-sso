<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

interface SsoDriver
{
    public function protocol(): string;

    public function start(Request $request, Tenant $tenant, Connection $connection): DriverStartResult;

    public function handleCallback(Request $request, Tenant $tenant, Connection $connection): DriverCallbackResult;
}
