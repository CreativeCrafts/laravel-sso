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
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new RuntimeException('Unable to generate RSA keypair.');
        }

        $privateKeyPem = '';

        if (!openssl_pkey_export($key, $privateKeyPem) || $privateKeyPem === '') {
            throw new RuntimeException('Unable to export private key.');
        }

        $csr = openssl_csr_new(['commonName' => 'Test'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);

        if ($cert === false) {
            throw new RuntimeException('Unable to self-sign certificate.');
        }

        $publicCertPem = '';

        if (!openssl_x509_export($cert, $publicCertPem) || $publicCertPem === '') {
            throw new RuntimeException('Unable to export certificate.');
        }

        return [
            'private' => $privateKeyPem,
            'public_cert_pem' => $publicCertPem,
        ];
    }

    /**
     * @param array<string, array<int, string>|string> $attributes
     */
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
        array $attributes = [],
    ): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $response = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:protocol', 'samlp:Response');
        $response->setAttribute('ID', '_resp');
        $response->setAttribute('Version', '2.0');
        $response->setAttribute('Destination', $destination);
        $doc->appendChild($response);

        $responseIssuer = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Issuer');
        $responseIssuer->nodeValue = $issuer;
        $response->appendChild($responseIssuer);

        $assertion = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Assertion');
        $assertion->setAttribute('ID', '_assert');
        $assertion->setAttribute('Version', '2.0');
        $response->appendChild($assertion);

        $assertionIssuer = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Issuer');
        $assertionIssuer->nodeValue = $issuer;
        $assertion->appendChild($assertionIssuer);

        $subject = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Subject');
        $assertion->appendChild($subject);

        $nameIdElement = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:NameID');
        $nameIdElement->nodeValue = $nameId;
        $subject->appendChild($nameIdElement);

        $subjectConfirmation = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:SubjectConfirmation');
        $subjectConfirmation->setAttribute('Method', 'urn:oasis:names:tc:SAML:2.0:cm:bearer');
        $subject->appendChild($subjectConfirmation);

        $subjectConfirmationData = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:SubjectConfirmationData');
        $subjectConfirmationData->setAttribute('Recipient', $recipient);
        $subjectConfirmationData->setAttribute('NotOnOrAfter', $notOnOrAfterIso);
        $subjectConfirmation->appendChild($subjectConfirmationData);

        $conditions = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Conditions');
        $conditions->setAttribute('NotBefore', $notBeforeIso);
        $conditions->setAttribute('NotOnOrAfter', $notOnOrAfterIso);
        $assertion->appendChild($conditions);

        $audienceRestriction = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:AudienceRestriction');
        $conditions->appendChild($audienceRestriction);

        $audienceElement = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Audience');
        $audienceElement->nodeValue = $audience;
        $audienceRestriction->appendChild($audienceElement);

        if ($attributes !== []) {
            $attributeStatement = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:AttributeStatement');
            $assertion->appendChild($attributeStatement);

            foreach ($attributes as $name => $values) {
                $normalizedValues = is_array($values) ? $values : [$values];

                if ($name === '' || $normalizedValues === []) {
                    continue;
                }

                $attribute = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:Attribute');
                $attribute->setAttribute('Name', $name);

                foreach ($normalizedValues as $value) {
                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    $attributeValue = $doc->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:AttributeValue');
                    $attributeValue->nodeValue = $value;
                    $attribute->appendChild($attributeValue);
                }

                if ($attribute->childNodes->length > 0) {
                    $attributeStatement->appendChild($attribute);
                }
            }
        }

        $assertion->setIdAttribute('ID', true);

        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $assertion,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['id_name' => 'ID', 'overwrite' => false, 'force_uri' => true],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKeyPem, false);

        $signature->sign($key);
        $signature->add509Cert($publicCertPem, true, false);
        $signature->insertSignature($assertion, $assertion->firstChild);

        $xml = $doc->saveXML();

        if (!is_string($xml) || $xml === '') {
            throw new RuntimeException('Unable to serialize signed SAML XML.');
        }

        return $xml;
    }
}
