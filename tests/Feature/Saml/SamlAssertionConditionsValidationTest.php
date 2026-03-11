<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionConditionsInvalid;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlFactory;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

function samlNowIso(int $secondsOffset = 0): string
{
    return now('UTC')->addSeconds($secondsOffset)->format('Y-m-d\TH:i:s\Z');
}

it('accepts when audience recipient destination and time are valid', function () {
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $tenant = 'tenant-ulid-1';
    $idp = '1';

    $acs = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
    $audience = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: $audience,
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $signed = app(SamlSignatureValidator::class)->validate($xml, [$keys['public_cert_pem']]);

    app(SamlAssertionConditionsValidator::class)->validate(
        signed: $signed,
        expectedAudience: $audience,
        expectedRecipient: $acs,
        expectedDestination: $acs,
        clockSkewSeconds: 60,
        requireAudience: true,
        requireRecipient: true,
        requireDestination: true,
    );

    expect(true)->toBeTrue();
});

it('rejects when audience mismatches', function () {
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $tenant = 'tenant-ulid-2';
    $idp = '2';

    $acs = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);

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

    $signed = app(SamlSignatureValidator::class)->validate($xml, [$keys['public_cert_pem']]);

    expect(fn () => app(SamlAssertionConditionsValidator::class)->validate(
        signed: $signed,
        expectedAudience: route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true),
        expectedRecipient: $acs,
        expectedDestination: $acs,
        clockSkewSeconds: 60,
        requireAudience: true,
        requireRecipient: true,
        requireDestination: true,
    ))->toThrow(SamlAssertionConditionsInvalid::class);
});

it('rejects when recipient mismatches', function () {
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $tenant = 'tenant-ulid-3';
    $idp = '3';

    $acs = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
    $audience = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: 'https://wrong-recipient.example',
        audience: $audience,
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $signed = app(SamlSignatureValidator::class)->validate($xml, [$keys['public_cert_pem']]);

    expect(fn () => app(SamlAssertionConditionsValidator::class)->validate(
        signed: $signed,
        expectedAudience: $audience,
        expectedRecipient: $acs,
        expectedDestination: $acs,
        clockSkewSeconds: 60,
        requireAudience: true,
        requireRecipient: true,
        requireDestination: true,
    ))->toThrow(SamlAssertionConditionsInvalid::class);
});

it('rejects when destination mismatches', function () {
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $tenant = 'tenant-ulid-4';
    $idp = '4';

    $acs = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
    $audience = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: 'https://wrong-destination.example',
        recipient: $acs,
        audience: $audience,
        notBeforeIso: samlNowIso(-30),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $signed = app(SamlSignatureValidator::class)->validate($xml, [$keys['public_cert_pem']]);

    expect(fn () => app(SamlAssertionConditionsValidator::class)->validate(
        signed: $signed,
        expectedAudience: $audience,
        expectedRecipient: $acs,
        expectedDestination: $acs,
        clockSkewSeconds: 60,
        requireAudience: true,
        requireRecipient: true,
        requireDestination: true,
    ))->toThrow(SamlAssertionConditionsInvalid::class);
});

it('rejects when not before is too far in the future beyond skew', function () {
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $tenant = 'tenant-ulid-5';
    $idp = '5';

    $acs = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
    $audience = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: $audience,
        notBeforeIso: samlNowIso(120),
        notOnOrAfterIso: samlNowIso(300),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $signed = app(SamlSignatureValidator::class)->validate($xml, [$keys['public_cert_pem']]);

    expect(fn () => app(SamlAssertionConditionsValidator::class)->validate(
        signed: $signed,
        expectedAudience: $audience,
        expectedRecipient: $acs,
        expectedDestination: $acs,
        clockSkewSeconds: 10,
        requireAudience: true,
        requireRecipient: true,
        requireDestination: true,
    ))->toThrow(SamlAssertionConditionsInvalid::class);
});

it('rejects when not on or after is expired beyond skew', function () {
    $keys = SamlTestXmlFactory::generateRsaCertPair();

    $tenant = 'tenant-ulid-6';
    $idp = '6';

    $acs = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
    $audience = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

    $xml = SamlTestXmlFactory::signedResponseWithAssertionConditions(
        issuer: 'https://issuer.example',
        nameId: 'user@example.test',
        destination: $acs,
        recipient: $acs,
        audience: $audience,
        notBeforeIso: samlNowIso(-300),
        notOnOrAfterIso: samlNowIso(-120),
        privateKeyPem: $keys['private'],
        publicCertPem: $keys['public_cert_pem'],
    );

    $signed = app(SamlSignatureValidator::class)->validate($xml, [$keys['public_cert_pem']]);

    expect(fn () => app(SamlAssertionConditionsValidator::class)->validate(
        signed: $signed,
        expectedAudience: $audience,
        expectedRecipient: $acs,
        expectedDestination: $acs,
        clockSkewSeconds: 10,
        requireAudience: true,
        requireRecipient: true,
        requireDestination: true,
    ))->toThrow(SamlAssertionConditionsInvalid::class);
});
