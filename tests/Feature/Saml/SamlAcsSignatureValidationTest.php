<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlSig;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    config()->set('sso.saml.require_destination', false);
    config()->set('sso.saml.require_audience', false);
    config()->set('sso.saml.require_recipient', false);
});

it('accepts a SAMLResponse when assertion signature is valid for configured cert', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $keys = SamlTestXmlSig::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $xml = SamlTestXmlSig::makeMinimalSamlResponse('https://issuer.example', 'user@example.test');
    $signed = SamlTestXmlSig::signAssertion($xml, $keys['private'], $keys['public_cert_pem']);

    $resp = $this->post(route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id]), [
      'SAMLResponse' => SamlTestXmlSig::b64($signed),
    ]);

    $resp->assertNoContent();
});

it('rejects a SAMLResponse when cert does not match signature', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $keysGood = SamlTestXmlSig::generateRsaCertPair();
    $keysWrong = SamlTestXmlSig::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keysWrong['public_cert_pem']],
      ],
    ]);

    $xml = SamlTestXmlSig::makeMinimalSamlResponse('https://issuer.example', 'user@example.test');
    $signed = SamlTestXmlSig::signAssertion($xml, $keysGood['private'], $keysGood['public_cert_pem']);

    $resp = $this->post(route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id]), [
      'SAMLResponse' => SamlTestXmlSig::b64($signed),
    ]);

    $resp->assertStatus(500);
});

it('rejects a SAMLResponse when assertion is tampered after signing', function () {
    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $keys = SamlTestXmlSig::generateRsaCertPair();

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
      ],
    ]);

    $xml = SamlTestXmlSig::makeMinimalSamlResponse('https://issuer.example', 'user@example.test');
    $signed = SamlTestXmlSig::signAssertion($xml, $keys['private'], $keys['public_cert_pem']);

    $tampered = str_replace('user@example.test', 'attacker@example.test', $signed);

    $resp = $this->post(route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id]), [
      'SAMLResponse' => SamlTestXmlSig::b64($tampered),
    ]);

    $resp->assertStatus(500);
});
