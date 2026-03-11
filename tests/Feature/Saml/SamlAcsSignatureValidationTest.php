<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlSig;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('accepts a signed assertion when the configured certificate matches', function () {
    $keys = SamlTestXmlSig::generateRsaCertPair();

    $xml = SamlTestXmlSig::makeMinimalSamlResponse('https://issuer.example', 'user@example.test');
    $signed = SamlTestXmlSig::signAssertion($xml, $keys['private'], $keys['public_cert_pem']);

    $validated = app(SamlSignatureValidator::class)->validate($signed, [$keys['public_cert_pem']]);

    expect($validated->validatedAssertionSignature)->toBeTrue();
});

it('rejects a signed assertion when the configured certificate does not match', function () {
    $keysGood = SamlTestXmlSig::generateRsaCertPair();
    $keysWrong = SamlTestXmlSig::generateRsaCertPair();

    $xml = SamlTestXmlSig::makeMinimalSamlResponse('https://issuer.example', 'user@example.test');
    $signed = SamlTestXmlSig::signAssertion($xml, $keysGood['private'], $keysGood['public_cert_pem']);

    expect(fn () => app(SamlSignatureValidator::class)->validate($signed, [$keysWrong['public_cert_pem']]))
      ->toThrow(SamlSignatureInvalid::class);
});

it('rejects a tampered assertion after signing', function () {
    $keys = SamlTestXmlSig::generateRsaCertPair();

    $xml = SamlTestXmlSig::makeMinimalSamlResponse('https://issuer.example', 'user@example.test');
    $signed = SamlTestXmlSig::signAssertion($xml, $keys['private'], $keys['public_cert_pem']);

    $tampered = str_replace('user@example.test', 'attacker@example.test', $signed);

    expect(fn () => app(SamlSignatureValidator::class)->validate($tampered, [$keys['public_cert_pem']]))
      ->toThrow(SamlSignatureInvalid::class);
});
