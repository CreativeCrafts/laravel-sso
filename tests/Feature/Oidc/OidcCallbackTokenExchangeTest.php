<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Drivers\OidcDriver;
use CreativeCrafts\LaravelSso\Exceptions\OidcTokenExchangeFailed;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(TestCase::class);

function jwt(array $payload): string
{
    $header = ['alg' => 'none', 'typ' => 'JWT'];

    $enc = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    return $enc($header) . '.' . $enc($payload) . '.';
}

it('exchanges code for tokens and returns claims (id_token)', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'discovery_enabled' => false,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
          'userinfo' => 'https://idp.example/userinfo',
        ],
        'client_id' => 'client-123',
        'client_secret' => 'secret-xyz',
        'redirect_uri' => 'https://app.example/sso/callback',
        'userinfo_enabled' => false,
      ],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    // Ensure relation exists for the driver
    $connection->load('identityProvider');

    $attempts = app(AuthAttemptService::class);
    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
        codeVerifier: 'verifier-123',
    );

    Http::fake([
      'https://idp.example/token' => Http::response([
        'access_token' => 'access-1',
        'token_type' => 'Bearer',
        'id_token' => jwt(['sub' => 'sub-1', 'email' => 'user@example.test', 'name' => 'User One']),
      ], 200),
    ]);

    $useCase = app(HandleCallback::class);

    $request = Request::create('/sso/callback', 'GET', [
      'state' => $attempt->state,
      'code' => 'code-123',
    ]);

    $result = $useCase->handle($request, $tenant, $connection->id);

    expect($result->authenticated)
      ->toBeTrue()
      ->and($result->subject)->toBe('sub-1')
      ->and($result->email)->toBe('user@example.test')
      ->and($result->displayName)->toBe('User One');
});

it('optionally fetches userinfo and merges claims', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'discovery_enabled' => false,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
          'userinfo' => 'https://idp.example/userinfo',
        ],
        'client_id' => 'client-123',
        'redirect_uri' => 'https://app.example/sso/callback',
        'userinfo_enabled' => true,
      ],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $connection->load('identityProvider');

    $attempts = app(AuthAttemptService::class);
    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
        codeVerifier: 'verifier-123',
    );

    Http::fake([
      'https://idp.example/token' => Http::response([
        'access_token' => 'access-1',
        'token_type' => 'Bearer',
        'id_token' => jwt(['sub' => 'sub-1', 'email' => 'old@example.test', 'name' => 'Old Name']),
      ], 200),

      'https://idp.example/userinfo' => Http::response([
        'sub' => 'sub-1',
        'email' => 'user@example.test',
        'name' => 'User One',
      ], 200),
    ]);

    $useCase = app(HandleCallback::class);

    $request = Request::create('/sso/callback', 'GET', [
      'state' => $attempt->state,
      'code' => 'code-123',
    ]);

    $result = $useCase->handle($request, $tenant, $connection->id);

    expect($result->authenticated)
      ->toBeTrue()
      ->and($result->email)->toBe('user@example.test')
      ->and($result->displayName)->toBe('User One');
});

it('maps invalid_grant to token exchange failure', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'discovery_enabled' => false,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
        ],
        'client_id' => 'client-123',
        'redirect_uri' => 'https://app.example/sso/callback',
      ],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $connection->load('identityProvider');

    $attempts = app(AuthAttemptService::class);
    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
        codeVerifier: 'verifier-123',
    );

    Http::fake([
      'https://idp.example/token' => Http::response([
        'error' => 'invalid_grant',
        'error_description' => 'The code has expired',
      ], 400),
    ]);

    $useCase = app(HandleCallback::class);

    $request = Request::create('/sso/callback', 'GET', [
      'state' => $attempt->state,
      'code' => 'bad-code',
    ]);

    $fn = fn () => $useCase->handle($request, $tenant, $connection->id);

    expect($fn)->toThrow(OidcTokenExchangeFailed::class);
});
