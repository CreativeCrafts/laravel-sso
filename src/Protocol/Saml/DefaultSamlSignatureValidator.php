<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Exceptions\SamlMetadataParseFailed;
use CreativeCrafts\LaravelSso\Exceptions\SamlResponseStatusInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureMissing;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;

final class DefaultSamlSignatureValidator implements SamlSignatureValidator
{
    private const string NS_SAML_PROTOCOL = 'urn:oasis:names:tc:SAML:2.0:protocol';
    private const string NS_SAML_ASSERTION = 'urn:oasis:names:tc:SAML:2.0:assertion';
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * @param array<int, string> $signingCertificatesPem
     */
    public function validate(string $xml, array $signingCertificatesPem): SamlSignedXml
    {
        $doc = $this->loadXml($xml);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('samlp', self::NS_SAML_PROTOCOL);
        $xpath->registerNamespace('saml', self::NS_SAML_ASSERTION);
        $xpath->registerNamespace('ds', self::NS_DS);

        $this->assertStrictDocumentShape($xpath);
        $this->assertSuccessfulResponseStatus($xpath);
        $this->registerAllIdAttributes($doc);

        $response = $this->firstElement($xpath, '/samlp:Response');
        $assertion = $this->firstElement($xpath, '/samlp:Response/saml:Assertion');

        $validatedResponseId = null;
        $validatedAssertionId = null;

        $hasAnySignature = false;

        if ($response instanceof DOMElement && $this->hasSignature($xpath, $response)) {
            $hasAnySignature = true;
            $validatedResponseId = $this->verifySignedElement($response, $signingCertificatesPem);
        }

        if ($assertion instanceof DOMElement && $this->hasSignature($xpath, $assertion)) {
            $hasAnySignature = true;
            $validatedAssertionId = $this->verifySignedElement($assertion, $signingCertificatesPem);
        }

        if ($hasAnySignature === false) {
            throw SamlSignatureMissing::make();
        }

        if ($validatedResponseId === null && $validatedAssertionId === null) {
            throw SamlSignatureInvalid::make();
        }

        return new SamlSignedXml(
            document: $doc,
            validatedResponseSignature: $validatedResponseId !== null,
            validatedAssertionSignature: $validatedAssertionId !== null,
            validatedResponseId: $validatedResponseId,
            validatedAssertionId: $validatedAssertionId,
        );
    }

    private function loadXml(string $xml): DOMDocument
    {
        try {
            $xml = ltrim($xml);

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $doc = new DOMDocument();
            // Preserve whitespace; stripping can break SignedInfo canonicalization integrity.
            $doc->preserveWhiteSpace = true;
            $doc->formatOutput = false;
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;

            $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if ($ok !== true || $errors !== []) {
                throw SamlMetadataParseFailed::invalidXml();
            }

            return $doc;
        } catch (Throwable $e) {
            throw SamlMetadataParseFailed::invalidXml($e);
        }
    }

    private function assertSuccessfulResponseStatus(DOMXPath $xpath): void
    {
        $statusCode = $this->firstElement($xpath, '/samlp:Response/samlp:Status/samlp:StatusCode');

        if (!$statusCode instanceof DOMElement) {
            throw SamlResponseStatusInvalid::make();
        }

        $value = trim($statusCode->getAttribute('Value'));

        if ($value === '' || !str_ends_with($value, ':Success')) {
            throw SamlResponseStatusInvalid::make($value !== '' ? $value : null);
        }
    }

    private function assertStrictDocumentShape(DOMXPath $xpath): void
    {
        if ($this->elementCount($xpath, '/samlp:Response') !== 1) {
            throw SamlSignatureInvalid::make();
        }

        if ($this->elementCount($xpath, '//saml:EncryptedAssertion') > 0) {
            throw SamlSignatureInvalid::make();
        }

        if ($this->elementCount($xpath, '//saml:Assertion') !== 1) {
            throw SamlSignatureInvalid::make();
        }

        if ($this->elementCount($xpath, '/samlp:Response/saml:Assertion') !== 1) {
            throw SamlSignatureInvalid::make();
        }

        if ($this->elementCount($xpath, '//saml:Assertion//saml:Assertion') > 0) {
            throw SamlSignatureInvalid::make();
        }

        if ($this->hasDuplicateIds($xpath)) {
            throw SamlSignatureInvalid::make();
        }
    }

    private function elementCount(DOMXPath $xpath, string $query): int
    {
        $nodes = $xpath->query($query);

        return $nodes === false ? 0 : $nodes->length;
    }

    private function hasDuplicateIds(DOMXPath $xpath): bool
    {
        $nodes = $xpath->query('//*[@ID]');

        if ($nodes === false) {
            return false;
        }

        $seen = [];

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $id = $node->getAttribute('ID');

            if ($id === '') {
                continue;
            }

            if (array_key_exists($id, $seen)) {
                return true;
            }

            $seen[$id] = true;
        }

        return false;
    }

    private function registerAllIdAttributes(DOMDocument $doc): void
    {
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//*[@ID]');

        if ($nodes === false) {
            return;
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $node->setIdAttribute('ID', true);
            }
        }
    }

    private function firstElement(DOMXPath $xpath, string $query): ?DOMElement
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length < 1) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function hasSignature(DOMXPath $xpath, DOMElement $scope): bool
    {
        $nodes = $xpath->query('ds:Signature', $scope);

        return $nodes !== false && $nodes->length > 0;
    }

    /**
     * @param array<int, string> $signingCertificatesPem
     */
    private function verifySignedElement(DOMElement $signedElement, array $signingCertificatesPem): ?string
    {
        try {
            $doc = $signedElement->ownerDocument;

            if (!$doc instanceof DOMDocument) {
                return null;
            }

            $signedElementId = $signedElement->getAttribute('ID');
            if ($signedElementId === '') {
                return null;
            }

            $signatureNode = $this->signatureNodeWithin($doc, $signedElement);

            if (!$signatureNode instanceof DOMElement) {
                return null;
            }

            if (!$this->signatureReferencesElement($doc, $signatureNode, $signedElementId)) {
                return null;
            }

            $dsig = new XMLSecurityDSig();
            $dsig->sigNode = $signatureNode;
            $dsig->idKeys = ['ID'];

            $dsig->canonicalizeSignedInfo();

            $refsOk = $dsig->validateReference();
            if ($refsOk !== true) {
                return null;
            }

            foreach ($signingCertificatesPem as $certPem) {
                if ($certPem === '') {
                    continue;
                }

                $key = $dsig->locateKey();
                if (!$key instanceof XMLSecurityKey) {
                    return null;
                }

                $key->loadKey($certPem, false, true);

                if ($dsig->verify($key) === 1) {
                    return $signedElementId;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function signatureReferencesElement(DOMDocument $doc, DOMElement $signatureNode, string $elementId): bool
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', self::NS_DS);

        $refs = $xpath->query('ds:SignedInfo/ds:Reference', $signatureNode);

        if ($refs === false || $refs->length < 1) {
            return false;
        }

        foreach ($refs as $ref) {
            if (!$ref instanceof DOMElement) {
                return false;
            }

            if ($ref->getAttribute('URI') !== '#' . $elementId) {
                return false;
            }
        }

        return true;
    }

    private function signatureNodeWithin(DOMDocument $doc, DOMElement $scope): ?DOMElement
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', self::NS_DS);

        $nodes = $xpath->query('ds:Signature', $scope);

        if ($nodes === false || $nodes->length < 1) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }
}
