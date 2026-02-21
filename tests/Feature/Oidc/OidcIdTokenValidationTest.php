<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Drivers\OidcDriver;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(TestCase::class);

function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function jwt_rs256(array $payload, string $privateKeyPem, string $kid): string
{
    $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid];

    $h = b64url(json_encode($header, JSON_THROW_ON_ERROR));
    $p = b64url(json_encode($payload, JSON_THROW_ON_ERROR));

    $data = $h . '.' . $p;

    $sig = '';
    openssl_sign($data, $sig, $privateKeyPem, OPENSSL_ALGO_SHA256);

    return $data . '.' . b64url($sig);
}

function jwks_from_public_key(string $publicKeyPem, string $kid): array
{
    $pub = openssl_pkey_get_public($publicKeyPem);
    $details = openssl_pkey_get_details($pub);

    /** @var array<string, mixed> $rsa */
    $rsa = $details['rsa'];

    return [
      'keys' => [
        [
          'kty' => 'RSA',
          'use' => 'sig',
          'alg' => 'RS256',
          'kid' => $kid,
          'n' => b64url($rsa['n']),
          'e' => b64url($rsa['e']),
        ],
      ],
    ];
}

it('validates id_token signature and claims (iss/aud/exp/nonce)', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    $kid = 'k1';

    $key = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privatePem);
    $publicPem = openssl_pkey_get_details($key)['key'];

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'issuer' => 'https://issuer.example',
        'discovery_enabled' => false,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
        ],
        'client_id' => 'client-123',
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

    $connection->load('identityProvider');

    $attempts = app(AuthAttemptService::class);
    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
        codeVerifier: 'verifier-123',
    );

    $idToken = jwt_rs256([
      'iss' => 'https://issuer.example',
      'aud' => 'client-123',
      'exp' => time() + 600,
      'nonce' => $attempt->nonce,
      'sub' => 'sub-1',
      'email' => 'user@example.test',
      'name' => 'User One',
    ], $privatePem, $kid);

    Http::fake([
      'https://idp.example/jwks' => Http::response(jwks_from_public_key($publicPem, $kid), 200),
      'https://idp.example/token' => Http::response([
        'access_token' => 'access-1',
        'token_type' => 'Bearer',
        'id_token' => $idToken,
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
      ->and($result->subject)->toBe('sub-1');
});

it('rejects nonce mismatch', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    $kid = 'k1';

    $key = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privatePem);
    $publicPem = openssl_pkey_get_details($key)['key'];

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'issuer' => 'https://issuer.example',
        'discovery_enabled' => false,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
        ],
        'client_id' => 'client-123',
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

    $connection->load('identityProvider');

    $attempts = app(AuthAttemptService::class);
    $attempt = $attempts->create(
        tenant: $tenant,
        protocol: 'oidc',
        connection: $connection,
        identityProvider: $idp,
        codeVerifier: 'verifier-123',
    );

    $idToken = jwt_rs256([
      'iss' => 'https://issuer.example',
      'aud' => 'client-123',
      'exp' => time() + 600,
      'nonce' => 'wrong-nonce',
      'sub' => 'sub-1',
    ], $privatePem, $kid);

    Http::fake([
      'https://idp.example/jwks' => Http::response(jwks_from_public_key($publicPem, $kid), 200),
      'https://idp.example/token' => Http::response([
        'access_token' => 'access-1',
        'token_type' => 'Bearer',
        'id_token' => $idToken,
      ], 200),
    ]);

    $useCase = app(HandleCallback::class);

    $request = Request::create('/sso/callback', 'GET', [
      'state' => $attempt->state,
      'code' => 'code-123',
    ]);

    $fn = fn () => $useCase->handle($request, $tenant, $connection->id);

    expect($fn)->toThrow(OidcIdTokenValidationFailed::class);
});
