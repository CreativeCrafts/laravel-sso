<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Http\Controllers\Concerns\HandlesCallbackResponse;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcIdTokenValidator;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('rejects unsafe IdP URLs by default', function (): void {
    /** @var UrlTrustPolicy $policy */
    $policy = app(UrlTrustPolicy::class);

    expect($policy->isTrusted('https://idp.example.com/.well-known/openid-configuration'))->toBeTrue()
        ->and($policy->isTrusted('http://idp.example.com/.well-known/openid-configuration'))->toBeFalse()
        ->and($policy->isTrusted('https://127.0.0.1/.well-known/openid-configuration'))->toBeFalse()
        ->and($policy->isTrusted('https://localhost/.well-known/openid-configuration'))->toBeFalse()
        ->and($policy->isTrusted('https://user:pass@idp.example.com/token'))->toBeFalse();
});

it('allows explicitly configured development IdP URLs', function (): void {
    config()->set('sso.security.allow_insecure_idp_urls', true);
    config()->set('sso.security.allow_private_idp_urls', true);

    /** @var UrlTrustPolicy $policy */
    $policy = app(UrlTrustPolicy::class);

    expect($policy->isTrusted('http://127.0.0.1:8080/.well-known/openid-configuration'))->toBeTrue();
});

it('encrypts PKCE verifier and stores request metadata on auth attempts', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => '01HR0000000000000000000001',
        'name' => 'Acme',
        'metadata' => [],
    ]);

    /** @var AuthAttemptService $service */
    $service = app(AuthAttemptService::class);

    $attempt = $service->create(
        tenant: $tenant,
        protocol: 'oidc',
        codeVerifier: 'plain-code-verifier',
        ip: '203.0.113.10',
        userAgent: str_repeat('A', 1200),
    );

    $raw = DB::table('sso_auth_attempts')->where('id', $attempt->id)->first();

    expect($attempt->code_verifier)->toBe('plain-code-verifier')
        ->and($raw)->not->toBeNull()
        ->and($raw->code_verifier)->not->toBe('plain-code-verifier')
        ->and($raw->ip)->toBe('203.0.113.10')
        ->and(strlen((string) $raw->user_agent))->toBe(1024);
});

it('reserves auth attempts before validation and only consumes after success', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => '01HR0000000000000000000002',
        'name' => 'Acme',
        'metadata' => [],
    ]);

    /** @var AuthAttemptService $service */
    $service = app(AuthAttemptService::class);

    $attempt = $service->create(
        tenant: $tenant,
        protocol: 'oidc',
        withNonce: true,
    );

    $reserved = $service->reserveForValidation($tenant, $attempt->state);

    expect($reserved->status)->toBe(AuthAttempt::STATUS_VALIDATING)
        ->and($reserved->validating_at)->not->toBeNull()
        ->and($reserved->consumed_at)->toBeNull();

    $failed = $service->markValidationFailed($reserved);

    expect($failed->status)->toBe(AuthAttempt::STATUS_PENDING)
        ->and($failed->validating_at)->toBeNull()
        ->and($failed->failed_at)->not->toBeNull()
        ->and($failed->consumed_at)->toBeNull();

    $reservedAgain = $service->reserveForValidation($tenant, $attempt->state);
    $consumed = $service->markConsumed($reservedAgain);

    expect($consumed->status)->toBe(AuthAttempt::STATUS_CONSUMED)
        ->and($consumed->consumed_at)->not->toBeNull();
});

it('blocks protocol-relative callback redirects', function (): void {
    $controller = new class () {
        use HandlesCallbackResponse;

        public function redirectFor(Request $request, ?AuthAttempt $attempt): string
        {
            return $this->resolveRedirect($request, $attempt);
        }
    };

    $request = Request::create('https://app.example.com/sso/callback', 'GET');

    $protocolRelative = new AuthAttempt(['redirect_to' => '//evil.example.com/phish']);
    $localPath = new AuthAttempt(['redirect_to' => '/dashboard']);
    $sameOrigin = new AuthAttempt(['redirect_to' => 'https://app.example.com/dashboard']);
    $external = new AuthAttempt(['redirect_to' => 'https://evil.example.com/dashboard']);

    expect($controller->redirectFor($request, $protocolRelative))->toBe('/')
        ->and($controller->redirectFor($request, $localPath))->toBe('/dashboard')
        ->and($controller->redirectFor($request, $sameOrigin))->toBe('https://app.example.com/dashboard')
        ->and($controller->redirectFor($request, $external))->toBe('/');
});

it('requires azp for multi-audience OIDC ID tokens', function (): void {
    [$privateKey, $jwk] = testRsaKeyPair();

    $validator = new DefaultOidcIdTokenValidator(new class ($jwk) implements \CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher {
        /** @param array<string, mixed> $jwk */
        public function __construct(private readonly array $jwk)
        {
        }

        public function fetchKeys(IdentityProvider $identityProvider): array
        {
            return [$this->jwk];
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

    $token = testSignedJwt(
        privateKey: $privateKey,
        kid: 'key-one',
        claims: [
            'iss' => 'https://issuer.example.com',
            'sub' => 'subject-one',
            'aud' => ['client-one', 'other-client'],
            'exp' => time() + 600,
            'nonce' => 'nonce-one',
        ],
    );

    expect(fn () => $validator->validate($idp, $attempt, $token))
        ->toThrow(OidcIdTokenValidationFailed::class);
});

it('fails closed when JWT kid is unknown', function (): void {
    [$privateKey, $jwk] = testRsaKeyPair('key-one');

    $validator = new DefaultOidcIdTokenValidator(new class ($jwk) implements \CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher {
        /** @param array<string, mixed> $jwk */
        public function __construct(private readonly array $jwk)
        {
        }

        public function fetchKeys(IdentityProvider $identityProvider): array
        {
            return [$this->jwk];
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

    $token = testSignedJwt(
        privateKey: $privateKey,
        kid: 'unknown-key',
        claims: [
            'iss' => 'https://issuer.example.com',
            'sub' => 'subject-one',
            'aud' => 'client-one',
            'exp' => time() + 600,
            'nonce' => 'nonce-one',
        ],
    );

    expect(fn () => $validator->validate($idp, $attempt, $token))
        ->toThrow(OidcIdTokenValidationFailed::class);
});

it('rejects SAML responses with multiple assertions before signature acceptance', function (): void {
    $validator = new DefaultSamlSignatureValidator();

    $xml = <<<'XML'
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_response">
    <saml:Assertion ID="_assertion_one" />
    <saml:Assertion ID="_assertion_two" />
</samlp:Response>
XML;

    expect(fn () => $validator->validate($xml, []))
        ->toThrow(SamlSignatureInvalid::class);
});

/**
 * @return array{0: string, 1: array<string, mixed>}
 */
function testRsaKeyPair(string $kid = 'key-one'): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($resource === false) {
        throw new RuntimeException('Unable to create test RSA key.');
    }

    openssl_pkey_export($resource, $privateKey);
    $details = openssl_pkey_get_details($resource);

    if (!is_string($privateKey) || !is_array($details) || !isset($details['rsa']) || !is_array($details['rsa'])) {
        throw new RuntimeException('Unable to read test RSA key details.');
    }

    $rsa = $details['rsa'];
    $n = $rsa['n'] ?? null;
    $e = $rsa['e'] ?? null;

    if (!is_string($n) || !is_string($e)) {
        throw new RuntimeException('Unable to read test RSA modulus/exponent.');
    }

    return [
        $privateKey,
        [
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => $kid,
            'n' => testBase64UrlEncode($n),
            'e' => testBase64UrlEncode($e),
        ],
    ];
}

/**
 * @param array<string, mixed> $claims
 */
function testSignedJwt(string $privateKey, string $kid, array $claims): string
{
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT',
        'kid' => $kid,
    ];

    $encodedHeader = testBase64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
    $encodedClaims = testBase64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
    $signingInput = $encodedHeader . '.' . $encodedClaims;

    $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    if ($ok !== true) {
        throw new RuntimeException('Unable to sign test JWT.');
    }

    return $signingInput . '.' . testBase64UrlEncode($signature);
}

function testBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
