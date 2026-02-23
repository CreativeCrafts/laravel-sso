<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

final class SamlTestXmlFactory
{
    /**
     * @return array{private: string, public_cert_pem: string}
     */
    public static function generateRsaCertPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new RuntimeException('Unable to generate RSA keypair');
        }

        $priv = '';
        if (!openssl_pkey_export($key, $priv) || $priv === '') {
            throw new RuntimeException('Unable to export private key');
        }

        $csr = openssl_csr_new(['commonName' => 'Test'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);

        if ($cert === false) {
            throw new RuntimeException('Unable to self-sign cert');
        }

        $certPem = '';
        if (!openssl_x509_export($cert, $certPem) || $certPem === '') {
            throw new RuntimeException('Unable to export cert');
        }

        return ['private' => $priv, 'public_cert_pem' => $certPem];
    }

    public static function signedResponseWithAssertionConditions(
        string $issuer,
        string $nameId,
        string $destination,
        string $recipient,
        string $audience,
        string $notBeforeIso,
        string $notOnOrAfterIso,
        string $privateKeyPem,
        string $publicCertPem,
    ): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $resp = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:protocol', 'samlp:Response');
        $resp->setAttribute('ID', '_resp');
        $resp->setAttribute('Version', '2.0');
        $resp->setAttribute('Destination', $destination);
        $doc->appendChild($resp);

        $issuerEl = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Issuer');
        $issuerEl->nodeValue = $issuer;
        $resp->appendChild($issuerEl);

        $assertion = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Assertion');
        $assertion->setAttribute('ID', '_assert');
        $assertion->setAttribute('Version', '2.0');
        $resp->appendChild($assertion);

        $assertIssuer = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Issuer');
        $assertIssuer->nodeValue = $issuer;
        $assertion->appendChild($assertIssuer);

        $subject = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Subject');
        $assertion->appendChild($subject);

        $nameIdEl = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:NameID');
        $nameIdEl->nodeValue = $nameId;
        $subject->appendChild($nameIdEl);

        $sc = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:SubjectConfirmation');
        $sc->setAttribute('Method', 'urn:oasis:names:tc:SAML:2.0:cm:bearer');
        $subject->appendChild($sc);

        $scd = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:SubjectConfirmationData');
        $scd->setAttribute('Recipient', $recipient);
        $scd->setAttribute('NotOnOrAfter', $notOnOrAfterIso);
        $sc->appendChild($scd);

        $conditions = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Conditions');
        $conditions->setAttribute('NotBefore', $notBeforeIso);
        $conditions->setAttribute('NotOnOrAfter', $notOnOrAfterIso);
        $assertion->appendChild($conditions);

        $ar = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:AudienceRestriction');
        $conditions->appendChild($ar);

        $aud = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Audience');
        $aud->nodeValue = $audience;
        $ar->appendChild($aud);

        $assertion->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $dsig->addReference(
            $assertion,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['id_name' => 'ID', 'overwrite' => false, 'force_uri' => true],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKeyPem, false);

        $dsig->sign($key);
        $dsig->add509Cert($publicCertPem, true, false);
        $dsig->insertSignature($assertion, $assertion->firstChild);

        $out = $doc->saveXML();

        if (!is_string($out) || $out === '') {
            throw new RuntimeException('Unable to serialize signed SAML XML');
        }

        return $out;
    }
}
