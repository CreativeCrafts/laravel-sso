<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\SamlMetadataParseFailed;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlMetadataParser;

it('parses entityId, SSO URLs, optional SLO, and multiple signing certs (rotation)', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="https://idp.example/entity">
          <IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
            <KeyDescriptor use="signing">
              <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                  <ds:X509Certificate>MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA111111111111111111</ds:X509Certificate>
                </ds:X509Data>
              </ds:KeyInfo>
            </KeyDescriptor>
        
            <KeyDescriptor use="signing">
              <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                  <ds:X509Certificate>MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA222222222222222222</ds:X509Certificate>
                </ds:X509Data>
              </ds:KeyInfo>
            </KeyDescriptor>
        
            <SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://idp.example/sso/redirect" />
            <SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="https://idp.example/sso/post" />
        
            <SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://idp.example/slo/redirect" />
          </IDPSSODescriptor>
        </EntityDescriptor>
        XML;

    $parser = new DefaultSamlMetadataParser();
    $meta = $parser->parse($xml);

    expect($meta->entityId)
      ->toBe('https://idp.example/entity')
      ->and($meta->ssoRedirectUrl)->toBe('https://idp.example/sso/redirect')
      ->and($meta->ssoPostUrl)->toBe('https://idp.example/sso/post')
      ->and($meta->sloRedirectUrl)->toBe('https://idp.example/slo/redirect')
      ->and($meta->sloPostUrl)->toBeNull()
      ->and($meta->signingCertificatesPem)->toHaveCount(2)
      ->and($meta->primarySigningCertificatePem())->toBe($meta->signingCertificatesPem[0]);
});

it('fails fast on malformed XML', function () {
    $parser = new DefaultSamlMetadataParser();

    expect(fn () => $parser->parse('<not-xml'))->toThrow(SamlMetadataParseFailed::class);
});
