<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\SamlAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Protocol\Saml\SamlAuthnRequestSigner;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlSig;

it('signs a valid authn request xml', function (): void {
    $keys = SamlTestXmlSig::generateRsaCertPair();

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="_request123"
    Version="2.0"
    IssueInstant="2026-01-01T00:00:00Z"
    Destination="https://idp.example.test/sso"
    AssertionConsumerServiceURL="https://sp.example.test/acs">
    <saml:Issuer>https://sp.example.test</saml:Issuer>
</samlp:AuthnRequest>
XML;

    $signed = (new SamlAuthnRequestSigner())->sign($xml, $keys['private'], $keys['public_cert_pem']);

    expect($signed)
        ->toContain('<samlp:AuthnRequest')
        ->toContain('Signature')
        ->toContain('X509Certificate');
});

it('throws when authn request xml is malformed', function (): void {
    $keys = SamlTestXmlSig::generateRsaCertPair();

    (new SamlAuthnRequestSigner())->sign('not-xml', $keys['private'], $keys['public_cert_pem']);
})->throws(SamlAuthorizationRequestFailed::class);
