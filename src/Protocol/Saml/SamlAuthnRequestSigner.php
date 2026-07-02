<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Exceptions\SamlAuthorizationRequestFailed;
use DOMDocument;
use DOMElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

final class SamlAuthnRequestSigner
{
    public function sign(string $authnRequestXml, string $privateKeyPem, string $publicCertPem): string
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;

        $loaded = $doc->loadXML($authnRequestXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true) {
            throw SamlAuthorizationRequestFailed::signingFailed();
        }

        $root = $doc->documentElement;

        if (!$root instanceof DOMElement) {
            throw SamlAuthorizationRequestFailed::signingFailed();
        }

        if ($root->hasAttribute('ID')) {
            $root->setIdAttribute('ID', true);
        }

        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReferenceList(
            [$root],
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['id_name' => 'ID', 'overwrite' => false, 'force_uri' => true],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKeyPem, false);

        $signature->sign($key);
        $signature->add509Cert($publicCertPem, true, false);
        $signature->insertSignature($root, $root->firstChild);

        $xml = $doc->saveXML();

        if (!is_string($xml) || $xml === '') {
            throw SamlAuthorizationRequestFailed::signingFailed();
        }

        return $xml;
    }
}
