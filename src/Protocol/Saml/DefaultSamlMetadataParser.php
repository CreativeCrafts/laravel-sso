<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlMetadataParser;
use CreativeCrafts\LaravelSso\Exceptions\SamlMetadataParseFailed;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlIdpMetadata;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

final class DefaultSamlMetadataParser implements SamlMetadataParser
{
    private const string NS_MD = 'urn:oasis:names:tc:SAML:2.0:metadata';
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const string BINDING_HTTP_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
    private const string BINDING_HTTP_POST = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';

    public function parse(string $xml): SamlIdpMetadata
    {
        $doc = $this->loadXml($xml);
        $xpath = $this->xpath($doc);

        $entityId = $this->extractEntityId($xpath);
        if ($entityId === null) {
            throw SamlMetadataParseFailed::missingEntityId();
        }

        $idp = $this->firstIdpDescriptor($xpath);
        if (!$idp instanceof DOMElement) {
            throw SamlMetadataParseFailed::missingIdpDescriptor();
        }

        [$ssoRedirect, $ssoPost] = $this->extractSsoUrls($xpath, $idp);
        if ($ssoRedirect === null && $ssoPost === null) {
            throw SamlMetadataParseFailed::missingSsoService();
        }

        [$sloRedirect, $sloPost] = $this->extractSloUrls($xpath, $idp);

        $certs = $this->extractSigningCertificatesPem($xpath, $idp);
        if ($certs === []) {
            throw SamlMetadataParseFailed::missingSigningCertificate();
        }

        return new SamlIdpMetadata(
            entityId: $entityId,
            ssoRedirectUrl: $ssoRedirect,
            ssoPostUrl: $ssoPost,
            sloRedirectUrl: $sloRedirect,
            sloPostUrl: $sloPost,
            signingCertificatesPem: $certs,
        );
    }

    private function loadXml(string $xml): DOMDocument
    {
        try {
            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = false;
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
        } catch (SamlMetadataParseFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            throw SamlMetadataParseFailed::invalidXml($e);
        }
    }

    private function xpath(DOMDocument $doc): DOMXPath
    {
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('md', self::NS_MD);
        $xpath->registerNamespace('ds', self::NS_DS);

        return $xpath;
    }

    private function extractEntityId(DOMXPath $xpath): ?string
    {
        // Prefer root EntityDescriptor@entityID
        $entityId = $xpath->evaluate('string(/md:EntityDescriptor/@entityID)');
        if (is_string($entityId) && trim($entityId) !== '') {
            return trim($entityId);
        }

        // Fallback: EntitiesDescriptor contains EntityDescriptor children
        $entityId = $xpath->evaluate('string(/md:EntitiesDescriptor/md:EntityDescriptor[1]/@entityID)');
        if (is_string($entityId) && trim($entityId) !== '') {
            return trim($entityId);
        }

        return null;
    }

    private function firstIdpDescriptor(DOMXPath $xpath): ?DOMElement
    {
        $nodes = $xpath->query('//md:IDPSSODescriptor');
        if ($nodes === false || $nodes->length < 1) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractSsoUrls(DOMXPath $xpath, DOMElement $idp): array
    {
        $redirect = null;
        $post = null;

        $nodes = $xpath->query('md:SingleSignOnService', $idp);
        if ($nodes === false) {
            return [null, null];
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $binding = trim($node->getAttribute('Binding'));
            $location = trim($node->getAttribute('Location'));
            if ($location === '') {
                continue;
            }
            if ($binding === '') {
                continue;
            }

            if ($binding === self::BINDING_HTTP_REDIRECT && $redirect === null) {
                $redirect = $location;
            }

            if ($binding === self::BINDING_HTTP_POST && $post === null) {
                $post = $location;
            }
        }

        return [$redirect, $post];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractSloUrls(DOMXPath $xpath, DOMElement $idp): array
    {
        $redirect = null;
        $post = null;

        $nodes = $xpath->query('md:SingleLogoutService', $idp);
        if ($nodes === false) {
            return [null, null];
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $binding = trim($node->getAttribute('Binding'));
            $location = trim($node->getAttribute('Location'));
            if ($location === '') {
                continue;
            }
            if ($binding === '') {
                continue;
            }

            if ($binding === self::BINDING_HTTP_REDIRECT && $redirect === null) {
                $redirect = $location;
            }

            if ($binding === self::BINDING_HTTP_POST && $post === null) {
                $post = $location;
            }
        }

        return [$redirect, $post];
    }

    /**
     * @return array<int, string>
     */
    private function extractSigningCertificatesPem(DOMXPath $xpath, DOMElement $idp): array
    {
        $certs = [];

        // Prefer KeyDescriptor[@use="signing"] first, then KeyDescriptor without @use
        $queries = [
          'md:KeyDescriptor[@use="signing"]//ds:X509Certificate',
          'md:KeyDescriptor[not(@use)]//ds:X509Certificate',
          'md:KeyDescriptor//ds:X509Certificate',
        ];

        foreach ($queries as $q) {
            $nodes = $xpath->query($q, $idp);
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $value = trim($node->textContent);

                if ($value === '') {
                    continue;
                }

                $base64 = $this->normalizeCertificateBase64($value);
                if ($base64 === null) {
                    continue;
                }

                $pem = $this->toPem($base64);

                if (!in_array($pem, $certs, true)) {
                    $certs[] = $pem;
                }
            }

            if ($certs !== []) {
                break;
            }
        }

        return $certs;
    }

    private function normalizeCertificateBase64(string $value): ?string
    {
        $value = trim($value);

        $value = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $value);

        $cleaned = preg_replace('/\s+/', '', $value);
        $value = is_string($cleaned) ? $cleaned : '';

        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return null;
        }

        return $value;
    }

    private function toPem(string $base64): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
          . chunk_split($base64, 64, "\n")
          . "-----END CERTIFICATE-----\n";
    }
}
