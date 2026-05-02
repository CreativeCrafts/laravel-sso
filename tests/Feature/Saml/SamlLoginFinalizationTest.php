<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlFactory;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

function ensureSamlIntegrationUsersTable(): void
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

function createSamlIntegrationFixture(array $trustedCertificates, bool $allowProvisioning = true): array
{
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant 01',
    ]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'SAML IdP',
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
        'settings' => $allowProvisioning ? ['allow_provisioning' => true] : [],
    ]);

    return [$tenant, $identityProvider, $connection];
}

it('completes the full SAML login flow and redirects to the intended location', function () {
    ensureSamlIntegrationUsersTable();

    $keys = SamlTestXmlFactory::generateRsaCertPair();

    [$tenant, $identityProvider, $connection] = createSamlIntegrationFixture([$keys['public_cert_pem']]);

    $intended = '/dashboard';

    $redirectResponse = $this->get(
        route('sso.redirect', [
            'tenant' => $tenant->ulid,
            'connection' => (string) $connection->id,
        ]) . '?redirect_to=' . urlencode($intended),
    );

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $relayState = $query['RelayState'] ?? null;

    expect($relayState)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $relayState)
        ->first();

    $requestId = $attempt?->context['saml_request_id'] ?? null;

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->toBeNull()
        ->and($attempt?->redirect_to)->toBe($intended)
        ->and($requestId)->toBeString()->not->toBe('');

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
        nameId: 'subject-123',
        destination: $acsUrl,
        recipient: $acsUrl,
        audience: $audience,
        inResponseTo: (string) $requestId,
        notBeforeIso: now('UTC')->subSeconds(30)->format('Y-m-d\TH:i:s\Z'),
        notOnOrAfterIso: now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s\Z'),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
        attributes: [
            'mail' => 'user@example.test',
            'displayName' => 'User One',
        ],
    );

    $callbackResponse = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]), [
        'SAMLResponse' => base64_encode($xml),
        'RelayState' => (string) $relayState,
    ]);

    $callbackResponse->assertRedirect($intended);

    expect(auth('web')->check())->toBeTrue();

    $external = ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('identity_provider_id', $identityProvider->id)
        ->where('provider_subject', 'subject-123')
        ->first();

    expect($external)->not->toBeNull();

    $attempt = $attempt?->fresh();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->not->toBeNull();
});

it('fails the full SAML callback flow when the response signature is invalid for the trusted identity provider certificate', function () {
    ensureSamlIntegrationUsersTable();

    $trustedKeys = SamlTestXmlFactory::generateRsaCertPair();
    $signingKeys = SamlTestXmlFactory::generateRsaCertPair();

    [$tenant, $identityProvider, $connection] = createSamlIntegrationFixture([$trustedKeys['public_cert_pem']]);

    $redirectResponse = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $relayState = $query['RelayState'] ?? null;

    expect($relayState)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $relayState)
        ->first();

    $requestId = $attempt?->context['saml_request_id'] ?? null;

    expect($attempt)->not->toBeNull()
        ->and($requestId)->toBeString()->not->toBe('');

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
        nameId: 'subject-invalid-signature',
        destination: $acsUrl,
        recipient: $acsUrl,
        audience: $audience,
        inResponseTo: (string) $requestId,
        notBeforeIso: now('UTC')->subSeconds(30)->format('Y-m-d\TH:i:s\Z'),
        notOnOrAfterIso: now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s\Z'),
        privateKeyPem: $signingKeys['private'],
        publicCertPem: $signingKeys['public_cert_pem'],
        attributes: [
            'mail' => 'invalid@example.test',
            'displayName' => 'Invalid Signature',
        ],
    );

    $callbackResponse = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]), [
        'SAMLResponse' => base64_encode($xml),
        'RelayState' => (string) $relayState,
    ]);

    $callbackResponse->assertStatus(500);

    expect(auth('web')->check())->toBeFalse();

    $external = ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('identity_provider_id', $identityProvider->id)
        ->where('provider_subject', 'subject-invalid-signature')
        ->first();

    expect($external)->toBeNull();

    $attempt = $attempt?->fresh();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->consumed_at)->toBeNull()
        ->and($attempt?->status)->toBe(AuthAttempt::STATUS_PENDING)
        ->and($attempt?->failed_at)->not->toBeNull();
});

it('fails the full SAML login flow when provisioning is denied by policy', function () {
    ensureSamlIntegrationUsersTable();

    app()->instance(ProvisioningPolicy::class, new class () implements ProvisioningPolicy {
        /**
         * @param array<string, mixed> $claims
         */
        public function allows(Tenant $tenant, Connection $connection, IdentityProvider $identityProvider, array $claims): bool
        {
            return false;
        }
    });

    $keys = SamlTestXmlFactory::generateRsaCertPair();

    [$tenant, $identityProvider, $connection] = createSamlIntegrationFixture([$keys['public_cert_pem']], false);

    $redirectResponse = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $relayState = $query['RelayState'] ?? null;

    expect($relayState)->toBeString()->not->toBe('');

    $attempt = AuthAttempt::query()
        ->where('tenant_id', $tenant->id)
        ->where('state', $relayState)
        ->first();

    $requestId = $attempt?->context['saml_request_id'] ?? null;

    expect($requestId)->toBeString()->not->toBe('');

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
        nameId: 'subject-denied',
        destination: $acsUrl,
        recipient: $acsUrl,
        audience: $audience,
        inResponseTo: (string) $requestId,
        notBeforeIso: now('UTC')->subSeconds(30)->format('Y-m-d\TH:i:s\Z'),
        notOnOrAfterIso: now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s\Z'),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
        attributes: [
            'mail' => 'denied@example.test',
            'displayName' => 'Denied User',
        ],
    );

    $callbackResponse = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => (string) $connection->id,
    ]), [
        'SAMLResponse' => base64_encode($xml),
        'RelayState' => (string) $relayState,
    ]);

    $callbackResponse->assertStatus(500);

    expect(auth('web')->check())->toBeFalse();

    $external = ExternalIdentity::query()
        ->where('tenant_id', $tenant->id)
        ->where('identity_provider_id', $identityProvider->id)
        ->where('provider_subject', 'subject-denied')
        ->first();

    expect($external)->toBeNull();
});
