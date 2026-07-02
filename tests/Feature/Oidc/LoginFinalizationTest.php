<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Drivers\OidcDriver;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\Support\OidcTestJwt;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

function ensureOidcIntegrationUsersTable(): void
{
    if (!Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);
}

function createOidcIntegrationFixture(bool $allowProvisioning = true): array
{
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'OIDC Tenant',
    ]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $identityProvider->id,
        'name' => 'Default',
        'enabled' => true,
        'guard' => 'web',
        'settings' => $allowProvisioning ? ['allow_provisioning' => true] : [],
    ]);

    $issuer = 'https://issuer.example';

    $identityProvider->forceFill([
        'config' => [
            'issuer' => $issuer,
            'discovery_enabled' => false,
            'endpoints' => [
                'authorization' => 'https://idp.example/authorize',
                'token' => 'https://idp.example/token',
                'jwks' => 'https://idp.example/jwks',
                'userinfo' => 'https://idp.example/userinfo',
            ],
            'client_id' => 'client-123',
            'client_secret' => 'secret-xyz',
            'redirect_uri' => route('sso.oidc.callback', [
                'tenant' => $tenant->ulid,
                'connection' => $connection->ulid,
            ], true),
            'userinfo_enabled' => false,
        ],
    ])->save();

    return [$tenant, $identityProvider->fresh(), $connection, $issuer];
}

it('completes the full OIDC login flow through the real driver and redirects to the intended location', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    ensureOidcIntegrationUsersTable();

    [$tenant, $identityProvider, $connection, $issuer] = createOidcIntegrationFixture();

    $intended = '/dashboard';

    $redirectResponse = $this->get(
        route('sso.redirect', [
            'tenant' => $tenant->ulid,
            'connection' => $connection->ulid,
        ]) . '?redirect_to=' . urlencode($intended),
    );

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;

    expect($state)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $state)
        ->first();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->toBeNull()
        ->and($attempt?->redirect_to)->toBe($intended);

    $kid = 'oidc-k1';
    $keys = OidcTestJwt::generateRsaKeypair();

    $idToken = OidcTestJwt::jwtRs256([
        'iss' => $issuer,
        'aud' => 'client-123',
        'exp' => time() + 600,
        'nonce' => $attempt?->nonce,
        'sub' => 'sub-oidc-1',
        'email' => 'user@example.test',
        'email_verified' => true,
        'name' => 'User One',
    ], $keys['private'], $kid);

    Http::fake([
        'https://idp.example/jwks' => Http::response(
            OidcTestJwt::jwksFromPublicKey($keys['public'], $kid),
            200,
        ),
        'https://idp.example/token' => Http::response([
            'access_token' => 'access-1',
            'token_type' => 'Bearer',
            'id_token' => $idToken,
        ], 200),
    ]);

    $callbackResponse = $this->get(route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]) . '?state=' . urlencode((string) $state) . '&code=' . urlencode('code-123'));

    $callbackResponse->assertRedirect($intended);

    expect(auth('web')->check())->toBeTrue();

    $external = ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('identity_provider_id', $identityProvider->id)
        ->where('provider_subject', 'sub-oidc-1')
        ->first();

    expect($external)->not->toBeNull();

    $attempt = $attempt?->fresh();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->not->toBeNull();
});

it('fails the full OIDC callback flow when the id token nonce does not match the auth attempt', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    ensureOidcIntegrationUsersTable();

    [$tenant, $identityProvider, $connection, $issuer] = createOidcIntegrationFixture();

    $redirectResponse = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;

    expect($state)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $state)
        ->first();

    expect($attempt)->not->toBeNull();

    $kid = 'oidc-k2';
    $keys = OidcTestJwt::generateRsaKeypair();

    $idToken = OidcTestJwt::jwtRs256([
        'iss' => $issuer,
        'aud' => 'client-123',
        'exp' => time() + 600,
        'nonce' => 'wrong-nonce',
        'sub' => 'sub-oidc-2',
        'email' => 'user@example.test',
        'name' => 'User One',
    ], $keys['private'], $kid);

    Http::fake([
        'https://idp.example/jwks' => Http::response(
            OidcTestJwt::jwksFromPublicKey($keys['public'], $kid),
            200,
        ),
        'https://idp.example/token' => Http::response([
            'access_token' => 'access-2',
            'token_type' => 'Bearer',
            'id_token' => $idToken,
        ], 200),
    ]);

    $callbackResponse = $this->get(route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]) . '?state=' . urlencode((string) $state) . '&code=' . urlencode('code-456'));

    $callbackResponse->assertStatus(401);

    expect(auth('web')->check())->toBeFalse();

    $attempt = $attempt?->fresh();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->toBeNull()
        ->and($attempt?->status)->toBe(AuthAttempt::STATUS_PENDING)
        ->and($attempt?->failed_at)->not->toBeNull();
});

it('fails the full OIDC callback flow when the auth attempt has expired before callback consumption', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    ensureOidcIntegrationUsersTable();

    [$tenant, $identityProvider, $connection] = createOidcIntegrationFixture();

    $redirectResponse = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;

    expect($state)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $state)
        ->first();

    expect($attempt)->not->toBeNull();

    $attempt?->forceFill([
        'expires_at' => now()->subSecond(),
    ])->save();

    $callbackResponse = $this->get(route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]) . '?state=' . urlencode((string) $state) . '&code=' . urlencode('code-expired'));

    $callbackResponse->assertStatus(410);

    expect(auth('web')->check())->toBeFalse();

    $attempt = $attempt?->fresh();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->toBeNull();
});

it('fails the full OIDC login flow when provisioning is denied by policy', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    ensureOidcIntegrationUsersTable();

    app()->instance(ProvisioningPolicy::class, new class () implements ProvisioningPolicy {
        /**
         * @param array<string, mixed> $claims
         */
        public function allows(Tenant $tenant, Connection $connection, IdentityProvider $identityProvider, array $claims): bool
        {
            return false;
        }
    });

    [$tenant, $identityProvider, $connection, $issuer] = createOidcIntegrationFixture(false);

    $redirectResponse = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;

    expect($state)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $state)
        ->first();

    expect($attempt)->not->toBeNull();

    $kid = 'oidc-k3';
    $keys = OidcTestJwt::generateRsaKeypair();

    $idToken = OidcTestJwt::jwtRs256([
        'iss' => $issuer,
        'aud' => 'client-123',
        'exp' => time() + 600,
        'nonce' => $attempt?->nonce,
        'sub' => 'sub-oidc-3',
        'email' => 'denied@example.test',
        'name' => 'Denied User',
    ], $keys['private'], $kid);

    Http::fake([
        'https://idp.example/jwks' => Http::response(
            OidcTestJwt::jwksFromPublicKey($keys['public'], $kid),
            200,
        ),
        'https://idp.example/token' => Http::response([
            'access_token' => 'access-3',
            'token_type' => 'Bearer',
            'id_token' => $idToken,
        ], 200),
    ]);

    $callbackResponse = $this->get(route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]) . '?state=' . urlencode((string) $state) . '&code=' . urlencode('code-denied'));

    $callbackResponse->assertStatus(403);

    expect(auth('web')->check())->toBeFalse();

    $external = ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('identity_provider_id', $identityProvider->id)
        ->where('provider_subject', 'sub-oidc-3')
        ->first();

    expect($external)->toBeNull();
});
