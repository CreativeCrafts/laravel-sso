<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use DOMDocument;
use DOMElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

final class SamlTestXmlSig
{
    /**
     * @return array{private: string, public_cert_pem: string}
     */
    public static function generateRsaCertPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

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

    public static function makeMinimalSamlResponse(string $issuer, string $nameId): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $resp = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:protocol', 'samlp:Response');
        $resp->setAttribute('ID', '_resp');
        $resp->setAttribute('Version', '2.0');
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

        $xml = $doc->saveXML();

        return is_string($xml) ? $xml : '';
    }

    public static function signAssertion(string $xml, string $privateKeyPem, string $publicCertPem): string
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $ok = $doc->loadXML(ltrim($xml), LIBXML_NONET);

        if ($ok !== true) {
            throw new RuntimeException('Unable to parse SAML XML');
        }

        $assertion = $doc->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);

        if (!$assertion instanceof DOMElement) {
            throw new RuntimeException('Missing Assertion');
        }

        if ($assertion->hasAttribute('ID')) {
            $assertion->setIdAttribute('ID', true);
        }

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

        self::assertVerifiableAssertionSignature($out, $publicCertPem);

        return $out;
    }

    public static function b64(string $xml): string
    {
        return base64_encode($xml);
    }

    private static function assertVerifiableAssertionSignature(string $signedXml, string $publicCertPem): void
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true; // preserve SignedInfo whitespace
        $doc->formatOutput = false;

        $ok = $doc->loadXML(ltrim($signedXml), LIBXML_NONET);

        if ($ok !== true) {
            throw new RuntimeException('Signed XML is not parseable');
        }

        $assertion = $doc->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);

        if (!$assertion instanceof DOMElement) {
            throw new RuntimeException('Signed XML missing Assertion');
        }

        if ($assertion->hasAttribute('ID')) {
            $assertion->setIdAttribute('ID', true);
        }

        $dsig = new XMLSecurityDSig();
        $sig = $dsig->locateSignature($assertion);

        if (!$sig instanceof DOMElement) {
            throw new RuntimeException('Signed XML missing ds:Signature');
        }

        $dsig->sigNode = $sig;
        $dsig->idKeys = ['ID'];

        $dsig->canonicalizeSignedInfo();

        $refsOk = $dsig->validateReference();
        if ($refsOk !== true) {
            throw new RuntimeException('Signed XML reference validation failed');
        }

        $key = $dsig->locateKey();
        if (!$key instanceof XMLSecurityKey) {
            throw new RuntimeException('Signed XML missing key info');
        }

        $key->loadKey($publicCertPem, false, true);

        if ($dsig->verify($key) !== 1) {
            throw new RuntimeException('Signed XML signature verification failed');
        }
    }
}
