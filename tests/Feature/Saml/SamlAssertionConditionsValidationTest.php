<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlFactory;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

function samlNowIso(int $secondsOffset = 0): string
{
    return now('UTC')->addSeconds($secondsOffset)->format('Y-m-d\TH:i:s\Z');
}

it('accepts when audience/recipient/destination/time are valid', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $acs = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);
    $aud = route('sso.saml.metadata', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: $aud,
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $resp = $this->post($acs, ['SAMLResponse' => base64_encode($xml)]);
    $resp->assertNoContent();
});

it('rejects when audience mismatches', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $acs = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: 'https://wrong-audience.example',
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $resp = $this->post($acs, ['SAMLResponse' => base64_encode($xml)]);
    $resp->assertStatus(500);
});

it('rejects when recipient mismatches', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $acs = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);
    $aud = route('sso.saml.metadata', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: 'https://wrong-recipient.example',
        audience: $aud,
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $resp = $this->post($acs, ['SAMLResponse' => base64_encode($xml)]);
    $resp->assertStatus(500);
});

it('rejects when destination mismatches', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $acs = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);
    $aud = route('sso.saml.metadata', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: 'https://wrong-destination.example',
        recipient: $acs,
        audience: $aud,
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $resp = $this->post($acs, ['SAMLResponse' => base64_encode($xml)]);
    $resp->assertStatus(500);
});

it('rejects when NotBefore is too far in the future beyond skew', function () {
    config()->set('sso.saml.clock_skew_seconds', 10);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $acs = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);
    $aud = route('sso.saml.metadata', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: $aud,
        notBeforeIso: samlNowIso(120),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $resp = $this->post($acs, ['SAMLResponse' => base64_encode($xml)]);
    $resp->assertStatus(500);
});

it('rejects when NotOnOrAfter is expired beyond skew', function () {
    config()->set('sso.saml.clock_skew_seconds', 10);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $acs = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);
    $aud = route('sso.saml.metadata', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: $aud,
        notBeforeIso: samlNowIso(-300),
        notOnOrAfterIso: samlNowIso(-120),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $resp = $this->post($acs, ['SAMLResponse' => base64_encode($xml)]);
    $resp->assertStatus(500);
});
