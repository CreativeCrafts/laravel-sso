<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlSig;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(TestCase::class);
uses(SsoTestHelpers::class);

it('signs saml authn requests when sp signing is enabled', function (): void {
    $keys = SamlTestXmlSig::generateRsaCertPair();

    Config::set('sso.saml.sp.sign_authn_requests', true);
    Config::set('sso.saml.sp.signing_private_key_pem', $keys['private']);
    Config::set('sso.saml.sp.signing_certificate_pem', $keys['public_cert_pem']);

    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);
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
        'settings' => [],
    ]);

    $response = $this->get(route('sso.redirect', [
        'tenant' => $tenant->ulid,
        'connection' => $connection->ulid,
    ]));

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $inflatedRequest = gzinflate(base64_decode((string) $query['SAMLRequest'], true));

    expect($inflatedRequest)->toContain('Signature');
});
