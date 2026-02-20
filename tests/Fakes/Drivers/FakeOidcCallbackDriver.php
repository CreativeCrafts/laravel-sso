<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Fakes\Drivers;

use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final class FakeOidcCallbackDriver implements SsoDriver
{
    public function protocol(): string
    {
        return 'oidc';
    }

    public function start(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverStartResult
    {
        return new DriverStartResult(
            redirectUrl: 'https://idp.example/authorize?state=' . $attempt->state,
            context: [],
        );
    }

    public function handleCallback(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverCallbackResult
    {
        return new DriverCallbackResult(
            authenticated: true,
            claims: ['sub' => 'user-123', 'email' => 'user@example.test'],
            context: ['driver' => 'fake'],
            error: null,
        );
    }
}
