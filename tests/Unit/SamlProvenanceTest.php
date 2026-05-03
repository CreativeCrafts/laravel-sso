<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlAssertionExtractor;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlClaimsMapper;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;
use DOMDocument;
use ReflectionMethod;
use ReflectionNamedType;

it('extracts claims from the signed assertion id', function (): void {
    $signed = samlProvenanceSignedXml(
        responseId: '_response_one',
        assertionId: '_assertion_one',
        validatedAssertionId: '_assertion_one',
    );

    $extracted = (new DefaultSamlAssertionExtractor())->extract($signed);

    expect($extracted['nameId'])->toBe('signed-subject')
        ->and($extracted['attributes']['email'][0])->toBe('signed@example.com');
});

it('extracts claims from the assertion directly under the signed response id', function (): void {
    $signed = samlProvenanceSignedXml(
        responseId: '_response_two',
        assertionId: '_assertion_two',
        validatedResponseId: '_response_two',
    );

    $extracted = (new DefaultSamlAssertionExtractor())->extract($signed);

    expect($extracted['nameId'])->toBe('signed-subject')
        ->and($extracted['attributes']['email'][0])->toBe('signed@example.com');
});

it('rejects assertion provenance mismatches during extraction', function (): void {
    $signed = samlProvenanceSignedXml(
        responseId: '_response_three',
        assertionId: '_assertion_three',
        validatedAssertionId: '_different_assertion',
    );

    expect(fn () => (new DefaultSamlAssertionExtractor())->extract($signed))
        ->toThrow(SamlSignatureInvalid::class);
});

it('normalizes claims from the validated SAML context', function (): void {
    $signed = samlProvenanceSignedXml(
        responseId: '_response_four',
        assertionId: '_assertion_four',
        validatedAssertionId: '_assertion_four',
    );

    $normalizer = new DefaultSamlClaimsNormalizer(
        extractor: new DefaultSamlAssertionExtractor(),
        mapper: new DefaultSamlClaimsMapper(),
    );

    $claims = $normalizer->normalize($signed);

    expect($claims->subject)->toBe('signed-subject')
        ->and($claims->email)->toBe('signed@example.com');
});

it('requires claims normalization to receive a validated signed XML context', function (): void {
    $method = new ReflectionMethod(SamlClaimsNormalizer::class, 'normalize');
    $parameter = $method->getParameters()[0];
    $type = $parameter->getType();

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);
    assert($type instanceof ReflectionNamedType);

    expect($type->getName())->toBe(SamlSignedXml::class);
});

function samlProvenanceSignedXml(
    string $responseId,
    string $assertionId,
    ?string $validatedResponseId = null,
    ?string $validatedAssertionId = null,
): SamlSignedXml {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="{$responseId}">
    <saml:Assertion ID="{$assertionId}">
        <saml:Subject>
            <saml:NameID>signed-subject</saml:NameID>
        </saml:Subject>
        <saml:AttributeStatement>
            <saml:Attribute Name="email">
                <saml:AttributeValue>signed@example.com</saml:AttributeValue>
            </saml:Attribute>
        </saml:AttributeStatement>
    </saml:Assertion>
</samlp:Response>
XML;

    $document = new DOMDocument();
    $document->preserveWhiteSpace = true;
    $document->loadXML($xml);

    return new SamlSignedXml(
        document: $document,
        validatedResponseSignature: $validatedResponseId !== null,
        validatedAssertionSignature: $validatedAssertionId !== null,
        validatedResponseId: $validatedResponseId,
        validatedAssertionId: $validatedAssertionId,
    );
}
