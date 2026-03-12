<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcDriver;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlFactory;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

function ensureThrottleUsersTable(): void
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

function createOidcThrottleFixture(array $connectionSettings = []): array
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
        'settings' => $connectionSettings,
    ]);

    return [$tenant, $identityProvider, $connection];
}

function createSamlThrottleFixture(array $trustedCertificates, array $connectionSettings = []): array
{
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'SAML Tenant',
    ]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'SAML',
        'protocol' => 'saml',
        'enabled' => true,
        'config' => [
            'saml_sso_url' => 'https://idp.example.test/sso',
            'saml_signing_certs_pem' => $trustedCertificates,
        ],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $identityProvider->id,
        'name' => 'Default',
        'enabled' => true,
        'guard' => 'web',
        'settings' => $connectionSettings,
    ]);

    return [$tenant, $identityProvider, $connection];
}

it('throttles the redirect endpoint and resets after the decay window', function () {
    config()->set('sso.drivers.oidc', FakeOidcDriver::class);
    config()->set('sso.throttling.redirect.max_attempts', 1);
    config()->set('sso.throttling.redirect.decay_minutes', 1);

    [$tenant, , $connection] = createOidcThrottleFixture();

    $url = route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]);

    $first = $this->get($url);
    $first->assertStatus(302);

    $second = $this->get($url);
    $second->assertStatus(429);
    $second->assertHeader('X-RateLimit-Limit');
    $second->assertHeader('X-RateLimit-Remaining');
    $second->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    $third = $this->get($url);
    $third->assertStatus(302);
});

it('uses an independent callback limiter bucket from the redirect limiter', function () {
    ensureThrottleUsersTable();

    config()->set('sso.drivers.oidc', FakeOidcCallbackDriver::class);
    config()->set('sso.throttling.redirect.max_attempts', 1);
    config()->set('sso.throttling.redirect.decay_minutes', 1);
    config()->set('sso.throttling.callback.max_attempts', 2);
    config()->set('sso.throttling.callback.decay_minutes', 1);

    [$tenant, , $connection] = createOidcThrottleFixture(['allow_provisioning' => true]);

    $redirectUrl = route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]);

    $redirectResponse = $this->get($redirectUrl);
    $redirectResponse->assertStatus(302);

    $redirectLocation = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($redirectLocation, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;
    expect($state)->toBeString()->not->toBeEmpty();

    $redirectLimited = $this->get($redirectUrl);
    $redirectLimited->assertStatus(429);

    $callbackUrl = route('sso.oidc.callback', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]);

    $callbackResponse = $this->get($callbackUrl . '?state=' . urlencode((string) $state));
    $callbackResponse->assertRedirect('/');

    expect(auth('web')->check())->toBeTrue();
});

it('throttles the saml acs endpoint and returns 429 with rate limit headers', function () {
    ensureThrottleUsersTable();

    config()->set('sso.throttling.acs.max_attempts', 1);
    config()->set('sso.throttling.acs.decay_minutes', 1);

    $keys = SamlTestXmlFactory::generateRsaCertPair();

    [$tenant, , $connection] = createSamlThrottleFixture([$keys['public_cert_pem']], ['allow_provisioning' => true]);

    $redirectResponse = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $relayState = $query['RelayState'] ?? null;
    expect($relayState)->toBeString()->not->toBe('');

    $acsUrl = route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ], true);

    $audience = route('sso.saml.metadata', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'subject-throttle',
        destination: $acsUrl,
        recipient: $acsUrl,
        audience: $audience,
        notBeforeIso: now('UTC')->subSeconds(30)->format('Y-m-d\TH:i:s\Z'),
        notOnOrAfterIso: now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s\Z'),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
        attributes: [
            'mail' => 'throttle@example.test',
            'displayName' => 'Throttle User',
        ],
    );

    $first = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]), [
        'SAMLResponse' => base64_encode($xml),
        'RelayState' => (string) $relayState,
    ]);

    $first->assertRedirect('/');

    $second = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]), [
        'SAMLResponse' => base64_encode($xml),
        'RelayState' => (string) $relayState,
    ]);

    $second->assertStatus(429);
    $second->assertHeader('X-RateLimit-Limit');
    $second->assertHeader('X-RateLimit-Remaining');
    $second->assertHeader('Retry-After');
});
