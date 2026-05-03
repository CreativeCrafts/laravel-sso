<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcIdTokenValidator;
use CreativeCrafts\LaravelSso\Tests\Support\OidcTestJwt;

it('uses auth_time instead of iat for OIDC max age validation', function (): void {
    config()->set('sso.oidc.id_token.max_age_seconds', 300);

    [$validator, $idp, $attempt, $keys] = oidcMaxAgeFixture();

    $token = OidcTestJwt::jwtRs256([
        'iss' => 'https://issuer.example.com',
        'sub' => 'subject-one',
        'aud' => 'client-one',
        'exp' => time() + 600,
        'iat' => time() - 3600,
        'auth_time' => time() - 60,
        'nonce' => 'nonce-one',
    ], $keys['private'], 'key-one');

    expect($validator->validate($idp, $attempt, $token)['sub'])->toBe('subject-one');
});

it('rejects stale OIDC auth_time when max age is configured', function (): void {
    config()->set('sso.oidc.id_token.max_age_seconds', 300);

    [$validator, $idp, $attempt, $keys] = oidcMaxAgeFixture();

    $token = OidcTestJwt::jwtRs256([
        'iss' => 'https://issuer.example.com',
        'sub' => 'subject-one',
        'aud' => 'client-one',
        'exp' => time() + 600,
        'iat' => time() - 60,
        'auth_time' => time() - 3600,
        'nonce' => 'nonce-one',
    ], $keys['private'], 'key-one');

    expect(fn () => $validator->validate($idp, $attempt, $token))
        ->toThrow(OidcIdTokenValidationFailed::class);
});

it('rejects OIDC tokens missing auth_time when max age is configured', function (): void {
    config()->set('sso.oidc.id_token.max_age_seconds', 300);

    [$validator, $idp, $attempt, $keys] = oidcMaxAgeFixture();

    $token = OidcTestJwt::jwtRs256([
        'iss' => 'https://issuer.example.com',
        'sub' => 'subject-one',
        'aud' => 'client-one',
        'exp' => time() + 600,
        'iat' => time() - 60,
        'nonce' => 'nonce-one',
    ], $keys['private'], 'key-one');

    expect(fn () => $validator->validate($idp, $attempt, $token))
        ->toThrow(OidcIdTokenValidationFailed::class);
});

/**
 * @return array{0: DefaultOidcIdTokenValidator, 1: IdentityProvider, 2: AuthAttempt, 3: array{private: string, public: string}}
 */
function oidcMaxAgeFixture(): array
{
    $keys = OidcTestJwt::generateRsaKeypair();
    $jwks = OidcTestJwt::jwksFromPublicKey($keys['public'], 'key-one');

    $validator = new DefaultOidcIdTokenValidator(new class ($jwks) implements OidcJwksFetcher {
        /** @param array{keys: array<int, array<string, mixed>>} $jwks */
        public function __construct(private readonly array $jwks)
        {
        }

        /** @return array<int, array<string, mixed>> */
        public function fetchKeys(IdentityProvider $identityProvider): array
        {
            return $this->jwks['keys'];
        }
    });

    $idp = new IdentityProvider();
    $idp->forceFill([
        'config' => [
            'issuer' => 'https://issuer.example.com',
            'client_id' => 'client-one',
        ],
    ]);

    $attempt = new AuthAttempt();
    $attempt->forceFill(['nonce' => 'nonce-one']);

    return [$validator, $idp, $attempt, $keys];
}
