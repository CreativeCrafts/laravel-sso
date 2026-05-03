<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('normalizes saml response xml into canonical claims', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                       xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                       ID="_response_claims_one">
          <saml:Assertion ID="_assertion_claims_one">
            <saml:Subject>
              <saml:NameID>sub-123</saml:NameID>
            </saml:Subject>
            <saml:AttributeStatement>
              <saml:Attribute Name="mail"><saml:AttributeValue>user@example.test</saml:AttributeValue></saml:Attribute>
              <saml:Attribute Name="displayName"><saml:AttributeValue>User One</saml:AttributeValue></saml:Attribute>
              <saml:Attribute Name="groups"><saml:AttributeValue>admin</saml:AttributeValue></saml:Attribute>
              <saml:Attribute Name="groups"><saml:AttributeValue>dev</saml:AttributeValue></saml:Attribute>
            </saml:AttributeStatement>
          </saml:Assertion>
        </samlp:Response>
        XML;

    $claims = app(SamlClaimsNormalizer::class)->normalize(
        samlClaimsSignedXml($xml, '_assertion_claims_one'),
    );

    expect($claims->subject)
      ->toBe('sub-123')
      ->and($claims->email)->toBe('user@example.test')
      ->and($claims->displayName)->toBe('User One')
      ->and($claims->groups)->toBe(['admin', 'dev'])
      ->and($claims->normalized)->toHaveKey('raw_saml');
});

it('honors configured attribute mapping keys', function () {
    config()->set('sso.saml.attribute_mapping.email', ['custom_email']);
    config()->set('sso.saml.attribute_mapping.display_name', ['custom_name']);
    config()->set('sso.saml.attribute_mapping.groups', ['custom_groups']);

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                       xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                       ID="_response_claims_two">
          <saml:Assertion ID="_assertion_claims_two">
            <saml:Subject>
              <saml:NameID>sub-456</saml:NameID>
            </saml:Subject>
            <saml:AttributeStatement>
              <saml:Attribute Name="custom_email"><saml:AttributeValue>mapped@example.test</saml:AttributeValue></saml:Attribute>
              <saml:Attribute Name="custom_name"><saml:AttributeValue>Mapped User</saml:AttributeValue></saml:Attribute>
              <saml:Attribute Name="custom_groups"><saml:AttributeValue>ops</saml:AttributeValue></saml:Attribute>
            </saml:AttributeStatement>
          </saml:Assertion>
        </samlp:Response>
        XML;

    $claims = app(SamlClaimsNormalizer::class)->normalize(
        samlClaimsSignedXml($xml, '_assertion_claims_two'),
    );

    expect($claims->subject)
      ->toBe('sub-456')
      ->and($claims->email)->toBe('mapped@example.test')
      ->and($claims->displayName)->toBe('Mapped User')
      ->and($claims->groups)->toBe(['ops']);
});

function samlClaimsSignedXml(string $xml, string $validatedAssertionId): SamlSignedXml
{
    $document = new \DOMDocument();
    $document->preserveWhiteSpace = true;
    $document->loadXML($xml);

    return new SamlSignedXml(
        document: $document,
        validatedResponseSignature: false,
        validatedAssertionSignature: true,
        validatedResponseId: null,
        validatedAssertionId: $validatedAssertionId,
    );
}
