<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('normalizes SAMLResponse XML into canonical Claims', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                       xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">
          <saml:Assertion>
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

    $claims = app(SamlClaimsNormalizer::class)->normalize($xml);

    expect($claims->subject)
      ->toBe('sub-123')
      ->and($claims->email)->toBe('user@example.test')
      ->and($claims->displayName)->toBe('User One')
      ->and($claims->groups)->toBe(['admin', 'dev'])
      ->and($claims->normalized)->toHaveKey('raw_saml');
});
