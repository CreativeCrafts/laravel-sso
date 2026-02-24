<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionExtractor;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;

final class DefaultSamlAssertionExtractor implements SamlAssertionExtractor
{
    private const string NS_SAMLP = 'urn:oasis:names:tc:SAML:2.0:protocol';
    private const string NS_SAML = 'urn:oasis:names:tc:SAML:2.0:assertion';

    /**
     * @return array{nameId: string, attributes: array<string, array<int, string>>}
     */
    public function extract(string $samlResponseXml): array
    {
        $doc = $this->loadXml($samlResponseXml);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('samlp', self::NS_SAMLP);
        $xpath->registerNamespace('saml', self::NS_SAML);

        $nameId = $this->firstText($xpath, '//saml:Assertion//saml:Subject//saml:NameID');

        /** @var array<string, array<int, string>> $attributes */
        $attributes = [];

        $attrNodes = $xpath->query('//saml:Assertion//saml:AttributeStatement//saml:Attribute');

        if ($attrNodes !== false) {
            foreach ($attrNodes as $attrNode) {
                if (!$attrNode instanceof DOMElement) {
                    continue;
                }

                $name = trim($attrNode->getAttribute('Name'));
                if ($name === '') {
                    continue;
                }

                $values = [];

                $valueNodes = $xpath->query('saml:AttributeValue', $attrNode);

                if ($valueNodes !== false) {
                    foreach ($valueNodes as $valueNode) {
                        if (!$valueNode instanceof DOMElement) {
                            continue;
                        }

                        $value = trim($valueNode->textContent);

                        if ($value !== '') {
                            $values[] = $value;
                        }
                    }
                }

                if ($values === []) {
                    continue;
                }

                $existing = $attributes[$name] ?? [];

                if ($existing === []) {
                    $attributes[$name] = $values;
                    continue;
                }

                $attributes[$name] = array_merge($existing, $values);
            }
        }

        return [
          'nameId' => $nameId,
          'attributes' => $attributes,
        ];
    }

    private function loadXml(string $xml): DOMDocument
    {
        try {
            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = true;
            $doc->formatOutput = false;
            $doc->resolveExternals = false;
            $doc->substituteEntities = false;

            $ok = $doc->loadXML(ltrim($xml), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if ($ok !== true || $errors !== []) {
                throw new RuntimeException('Invalid SAML XML.');
            }

            return $doc;
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid SAML XML.', 0, $e);
        }
    }

    private function firstText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length < 1) {
            return '';
        }

        $node = $nodes->item(0);

        if (!$node instanceof DOMElement) {
            return '';
        }

        return trim($node->textContent);
    }
}
