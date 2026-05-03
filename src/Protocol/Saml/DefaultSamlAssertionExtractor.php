<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionExtractor;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;
use DOMElement;
use DOMXPath;

final class DefaultSamlAssertionExtractor implements SamlAssertionExtractor
{
    private const string NS_SAMLP = 'urn:oasis:names:tc:SAML:2.0:protocol';
    private const string NS_SAML = 'urn:oasis:names:tc:SAML:2.0:assertion';

    /**
     * @return array{nameId: string, attributes: array<string, array<int, string>>}
     */
    public function extract(SamlSignedXml $signed): array
    {
        $xpath = $this->xpath($signed);
        $assertion = $this->trustedAssertion($signed, $xpath);

        $nameId = $this->firstText($xpath, 'saml:Subject/saml:NameID', $assertion);

        /** @var array<string, array<int, string>> $attributes */
        $attributes = [];

        $attrNodes = $xpath->query('saml:AttributeStatement/saml:Attribute', $assertion);

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

    private function xpath(SamlSignedXml $signed): DOMXPath
    {
        $xpath = new DOMXPath($signed->document);
        $xpath->registerNamespace('samlp', self::NS_SAMLP);
        $xpath->registerNamespace('saml', self::NS_SAML);

        return $xpath;
    }

    private function trustedAssertion(SamlSignedXml $signed, DOMXPath $xpath): DOMElement
    {
        if ($signed->validatedAssertionId !== null) {
            $assertion = $this->assertionById($xpath, $signed->validatedAssertionId);

            if ($assertion instanceof DOMElement) {
                return $assertion;
            }

            throw SamlSignatureInvalid::make();
        }

        if ($signed->validatedResponseId !== null) {
            $response = $this->responseById($xpath, $signed->validatedResponseId);

            if ($response instanceof DOMElement) {
                $assertion = $this->firstElement($xpath, 'saml:Assertion', $response);

                if ($assertion instanceof DOMElement) {
                    return $assertion;
                }
            }
        }

        throw SamlSignatureInvalid::make();
    }

    private function responseById(DOMXPath $xpath, string $id): ?DOMElement
    {
        $response = $this->firstElement($xpath, '/samlp:Response');

        if (!$response instanceof DOMElement) {
            return null;
        }

        return $response->getAttribute('ID') === $id ? $response : null;
    }

    private function assertionById(DOMXPath $xpath, string $id): ?DOMElement
    {
        $assertion = $this->firstElement($xpath, '/samlp:Response/saml:Assertion');

        if (!$assertion instanceof DOMElement) {
            return null;
        }

        return $assertion->getAttribute('ID') === $id ? $assertion : null;
    }

    private function firstElement(DOMXPath $xpath, string $query, ?DOMElement $context = null): ?DOMElement
    {
        $nodes = $xpath->query($query, $context);

        if ($nodes === false || $nodes->length < 1) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function firstText(DOMXPath $xpath, string $query, DOMElement $context): string
    {
        $node = $this->firstElement($xpath, $query, $context);

        if (!$node instanceof DOMElement) {
            return '';
        }

        return trim($node->textContent);
    }
}
