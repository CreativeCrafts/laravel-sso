<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Fakes\Drivers;

use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
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
        $normalized = [
            'sub' => 'user-123',
            'email' => 'user@example.test',
            'name' => 'User One',
            'email_verified' => null,
            'groups' => [],
        ];

        $canonical = new Claims(
            subject: 'user-123',
            email: 'user@example.test',
            displayName: 'User One',
            emailVerified: null,
            groups: [],
            normalized: $normalized,
        );

        return new DriverCallbackResult(
            authenticated: true,
            canonicalClaims: $canonical,
            subject: $canonical->subject,
            email: $canonical->email,
            displayName: $canonical->displayName,
            claims: $canonical->toArray(),
            context: [
                'driver' => 'fake',
                'userinfo_used' => true,
                'access_token' => 'super-secret-access-token',
                'id_token' => 'header.payload.signature',
                'raw_claims' => $normalized,
            ],
            error: null,
        );
    }
}
