<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcIdTokenValidator;

it('rejects present but non-numeric iat claims', function (): void {
    $fixture = oidcTemporalFixture([
        'iat' => 'not-a-number',
    ]);

    expect(fn () => $fixture['validator']->validate($fixture['idp'], $fixture['attempt'], $fixture['token']))
        ->toThrow(OidcIdTokenValidationFailed::class, 'iat must be numeric');
});

it('rejects present but non-numeric nbf claims', function (): void {
    $fixture = oidcTemporalFixture([
        'nbf' => 'not-a-number',
    ]);

    expect(fn () => $fixture['validator']->validate($fixture['idp'], $fixture['attempt'], $fixture['token']))
        ->toThrow(OidcIdTokenValidationFailed::class, 'nbf must be numeric');
});

it('rejects present but non-numeric auth_time claims even when max age is disabled', function (): void {
    config()->set('sso.oidc.id_token.max_age_seconds', null);

    $fixture = oidcTemporalFixture([
        'auth_time' => 'not-a-number',
    ]);

    expect(fn () => $fixture['validator']->validate($fixture['idp'], $fixture['attempt'], $fixture['token']))
        ->toThrow(OidcIdTokenValidationFailed::class, 'auth_time must be numeric');
});

it('allows missing auth_time when max age is disabled', function (): void {
    config()->set('sso.oidc.id_token.max_age_seconds', null);

    $fixture = oidcTemporalFixture();

    $claims = $fixture['validator']->validate($fixture['idp'], $fixture['attempt'], $fixture['token']);

    expect($claims['sub'])->toBe('user-one');
});

it('requires numeric auth_time when max age is enabled', function (): void {
    config()->set('sso.oidc.id_token.max_age_seconds', 300);

    $fixture = oidcTemporalFixture([
        'auth_time' => 'not-a-number',
    ]);

    expect(fn () => $fixture['validator']->validate($fixture['idp'], $fixture['attempt'], $fixture['token']))
        ->toThrow(OidcIdTokenValidationFailed::class, 'auth_time must be numeric');
});

/**
 * @param array<string, mixed> $claimOverrides
 * @return array{validator: DefaultOidcIdTokenValidator, idp: IdentityProvider, attempt: AuthAttempt, token: string}
 */
function oidcTemporalFixture(array $claimOverrides = []): array
{
    config()->set('sso.oidc.id_token.clock_skew_seconds', 0);

    $tenant = Tenant::query()->create([
        'ulid' => oidcTemporalUlid(),
        'name' => 'OIDC Temporal Tenant',
        'metadata' => [],
    ]);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'OIDC IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'issuer' => 'https://idp.example.test',
            'client_id' => 'client-one',
        ],
    ]);

    $attempt = AuthAttempt::query()->create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oidc',
        'state' => bin2hex(random_bytes(16)),
        'nonce' => 'nonce-one',
        'redirect_to' => null,
        'expires_at' => now()->addMinutes(5),
        'consumed_at' => null,
        'status' => AuthAttempt::STATUS_PENDING,
        'validating_at' => null,
        'failed_at' => null,
        'ip' => null,
        'user_agent' => null,
        'context' => [],
    ]);

    $keyPair = oidcTemporalRsaKeyPair();

    $claims = array_merge([
        'iss' => 'https://idp.example.test',
        'sub' => 'user-one',
        'aud' => 'client-one',
        'exp' => time() + 300,
        'iat' => time() - 5,
        'nonce' => 'nonce-one',
    ], $claimOverrides);

    $token = oidcTemporalSignedJwt(
        header: [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => 'test-key',
        ],
        claims: $claims,
        privateKeyPem: $keyPair['private'],
    );

    $jwks = new class ($keyPair['jwk']) implements OidcJwksFetcher {
        /** @param array<string, mixed> $jwk */
        public function __construct(private readonly array $jwk)
        {
        }

        public function fetchKeys(IdentityProvider $identityProvider): array
        {
            return [$this->jwk];
        }
    };

    return [
        'validator' => new DefaultOidcIdTokenValidator($jwks),
        'idp' => $idp,
        'attempt' => $attempt,
        'token' => $token,
    ];
}

/**
 * @return array{private: string, jwk: array{kty: string, use: string, kid: string, alg: string, n: string, e: string}}
 */
function oidcTemporalRsaKeyPair(): array
{
    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);

    if ($resource === false) {
        throw new RuntimeException('Unable to create RSA key pair for OIDC temporal claim test.');
    }

    $exported = openssl_pkey_export($resource, $privateKeyPem);

    if ($exported !== true || !is_string($privateKeyPem) || $privateKeyPem === '') {
        throw new RuntimeException('Unable to export RSA private key for OIDC temporal claim test.');
    }

    $details = openssl_pkey_get_details($resource);

    if (!is_array($details) || !isset($details['rsa']) || !is_array($details['rsa'])) {
        throw new RuntimeException('Unable to read RSA public key details for OIDC temporal claim test.');
    }

    $modulus = $details['rsa']['n'] ?? null;
    $exponent = $details['rsa']['e'] ?? null;

    if (!is_string($modulus) || !is_string($exponent)) {
        throw new RuntimeException('Unable to read RSA modulus or exponent for OIDC temporal claim test.');
    }

    return [
        'private' => $privateKeyPem,
        'jwk' => [
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => 'test-key',
            'alg' => 'RS256',
            'n' => oidcTemporalBase64UrlEncode($modulus),
            'e' => oidcTemporalBase64UrlEncode($exponent),
        ],
    ];
}

/**
 * @param array<string, mixed> $header
 * @param array<string, mixed> $claims
 */
function oidcTemporalSignedJwt(array $header, array $claims, string $privateKeyPem): string
{
    $encodedHeader = oidcTemporalBase64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
    $encodedClaims = oidcTemporalBase64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
    $signingInput = $encodedHeader . '.' . $encodedClaims;

    $signed = openssl_sign($signingInput, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);

    if ($signed !== true || !is_string($signature)) {
        throw new RuntimeException('Unable to sign OIDC temporal claim test JWT.');
    }

    return $signingInput . '.' . oidcTemporalBase64UrlEncode($signature);
}

function oidcTemporalBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function oidcTemporalUlid(): string
{
    return '01J' . strtoupper(substr(bin2hex(random_bytes(13)), 0, 23));
}
