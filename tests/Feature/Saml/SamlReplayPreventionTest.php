<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlFactory;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

it('rejects replay of the same saml relay state after the auth attempt is consumed', function () {
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

    $tenant = Tenant::query()->create([
      'ulid' => (string) Str::ulid(),
      'name' => 'Tenant Replay',
    ]);

    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_sso_url' => 'https://idp.example.test/sso',
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
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

    $redirectResponse = $this->get(route('sso.redirect', [
      'tenant' => $tenant->ulid,
      'idp' => (string) $connection->id,
    ]));

    $redirectResponse->assertRedirect();

    $location = (string) $redirectResponse->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $relayState = $query['RelayState'] ?? null;

    expect($relayState)->toBeString()->not->toBe('');

    $acsUrl = route('sso.saml.acs', [
      'tenant' => $tenant->ulid,
      'idp' => (string) $connection->id,
    ], true);

    $audience = route('sso.saml.metadata', [
      'tenant' => $tenant->ulid,
      'idp' => (string) $connection->id,
    ], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'subject-replay',
        destination: $acsUrl,
        recipient: $acsUrl,
        audience: $audience,
        notBeforeIso: now('UTC')->subSeconds(30)->format('Y-m-d\TH:i:s\Z'),
        notOnOrAfterIso: now('UTC')->addMinutes(5)->format('Y-m-d\TH:i:s\Z'),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
        attributes: [
            'email' => ['replay@example.test'],
            'displayName' => ['Replay User'],
        ],
    );

    $firstResponse = $this->post(route('sso.saml.acs', [
      'tenant' => $tenant->ulid,
      'idp' => (string) $connection->id,
    ]), [
      'SAMLResponse' => base64_encode($xml),
      'RelayState' => (string) $relayState,
    ]);

    $firstResponse->assertRedirect('/');

    $secondResponse = $this->post(route('sso.saml.acs', [
      'tenant' => $tenant->ulid,
      'idp' => (string) $connection->id,
    ]), [
      'SAMLResponse' => base64_encode($xml),
      'RelayState' => (string) $relayState,
    ]);

    $secondResponse->assertStatus(500);
});
