<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use DOMDocument;
use DOMException;
use Illuminate\Contracts\Routing\UrlGenerator;

final class SpMetadataGenerator
{
    private const string NS_MD = 'urn:oasis:names:tc:SAML:2.0:metadata';
    private const string PROTOCOL_SAML2 = 'urn:oasis:names:tc:SAML:2.0:protocol';

    public function __construct(
        private readonly UrlGenerator $url,
    ) {
    }

    /**
     * @throws DOMException
     */
    public function generate(string $tenant, string $idp): string
    {
        $metadataUrl = $this->url->route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);
        $acsUrl = $this->url->route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);

        $entityId = $this->entityId($metadataUrl);

        $binding = config('sso.saml.sp.acs_binding', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST');
        $binding = is_string($binding) && $binding !== '' ? $binding : 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $entityDescriptor = $doc->createElementNS(self::NS_MD, 'EntityDescriptor');
        $entityDescriptor->setAttribute('entityID', $entityId);
        $doc->appendChild($entityDescriptor);

        $sp = $doc->createElementNS(self::NS_MD, 'SPSSODescriptor');
        $sp->setAttribute('protocolSupportEnumeration', self::PROTOCOL_SAML2);
        $entityDescriptor->appendChild($sp);

        $acs = $doc->createElementNS(self::NS_MD, 'AssertionConsumerService');
        $acs->setAttribute('index', '0');
        $acs->setAttribute('isDefault', 'true');
        $acs->setAttribute('Binding', $binding);
        $acs->setAttribute('Location', $acsUrl);
        $sp->appendChild($acs);

        $xml = $doc->saveXML();

        return is_string($xml) ? $xml : '';
    }

    private function entityId(string $fallbackMetadataUrl): string
    {
        $configured = config('sso.saml.sp.entity_id');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $fallbackMetadataUrl;
    }
}
