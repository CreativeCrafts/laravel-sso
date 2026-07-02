<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('builds a redirect-based authn request for saml begin login', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant 01',
    ]);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'SAML IdP',
        'protocol' => 'saml',
        'enabled' => true,
        'config' => [
            'saml_sso_url' => 'https://idp.example.test/sso',
            'saml_signing_certs_pem' => ['-----BEGIN CERTIFICATE-----fake-----END CERTIFICATE-----'],
        ],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Default',
        'enabled' => true,
        'settings' => [],
    ]);

    $response = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]));

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith('https://idp.example.test/sso');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query)->toHaveKeys(['SAMLRequest', 'RelayState']);

    $decodedRequest = base64_decode((string) $query['SAMLRequest'], true);
    expect($decodedRequest)->toBeString()->not->toBeFalse();

    $inflatedRequest = gzinflate($decodedRequest);
    expect($inflatedRequest)
        ->toBeString()
        ->toContain('<samlp:AuthnRequest')
        ->toContain('AssertionConsumerServiceURL="')
        ->toContain('<saml:Issuer>')
        ->and($query['RelayState'])->toBeString()->not->toBe('');
});
