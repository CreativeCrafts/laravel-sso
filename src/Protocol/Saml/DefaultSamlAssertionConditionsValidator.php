<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use Carbon\CarbonImmutable;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionConditionsInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;
use DOMElement;
use DOMXPath;
use Exception;

final class DefaultSamlAssertionConditionsValidator implements SamlAssertionConditionsValidator
{
    private const string NS_SAML_PROTOCOL = 'urn:oasis:names:tc:SAML:2.0:protocol';
    private const string NS_SAML_ASSERTION = 'urn:oasis:names:tc:SAML:2.0:assertion';

    public function validate(
        SamlSignedXml $signed,
        string $expectedAudience,
        string $expectedRecipient,
        string $expectedDestination,
        int $clockSkewSeconds,
        bool $requireAudience,
        bool $requireRecipient,
        bool $requireDestination,
    ): void {
        $xpath = new DOMXPath($signed->document);
        $xpath->registerNamespace('samlp', self::NS_SAML_PROTOCOL);
        $xpath->registerNamespace('saml', self::NS_SAML_ASSERTION);

        $response = $this->trustedResponse($signed, $xpath);
        $assertion = $this->trustedAssertion($signed, $xpath, $response);

        if ($requireDestination) {
            $destination = $response instanceof DOMElement ? $response->getAttribute('Destination') : '';

            if ($destination === '' || $destination !== $expectedDestination) {
                throw SamlAssertionConditionsInvalid::destinationMismatch();
            }
        }

        $now = CarbonImmutable::now('UTC');
        $skew = max(0, $clockSkewSeconds);

        $conditions = $this->firstElement($xpath, 'saml:Conditions', $assertion);

        if ($conditions instanceof DOMElement) {
            $notBefore = $conditions->getAttribute('NotBefore');
            $notOnOrAfter = $conditions->getAttribute('NotOnOrAfter');

            if ($notBefore !== '') {
                try {
                    $nb = CarbonImmutable::parse($notBefore, 'UTC');
                } catch (Exception) {
                    throw SamlAssertionConditionsInvalid::invalidTimestamp();
                }
                if ($now->addSeconds($skew)->lt($nb)) {
                    throw SamlAssertionConditionsInvalid::notYetValid();
                }
            }

            if ($notOnOrAfter !== '') {
                try {
                    $noa = CarbonImmutable::parse($notOnOrAfter, 'UTC');
                } catch (Exception) {
                    throw SamlAssertionConditionsInvalid::invalidTimestamp();
                }
                if ($now->subSeconds($skew)->greaterThanOrEqualTo($noa)) {
                    throw SamlAssertionConditionsInvalid::expired();
                }
            }

            if ($requireAudience) {
                $aud = $this->firstElement($xpath, './/saml:AudienceRestriction/saml:Audience', $conditions);

                $audienceValue = $aud instanceof DOMElement ? trim($aud->textContent) : '';

                if ($audienceValue === '' || $audienceValue !== $expectedAudience) {
                    throw SamlAssertionConditionsInvalid::audienceMismatch();
                }
            }
        } elseif ($requireAudience) {
            throw SamlAssertionConditionsInvalid::audienceMismatch();
        }

        if ($requireRecipient) {
            $scd = $this->firstElement($xpath, './/saml:SubjectConfirmationData', $assertion);
            $recipient = $scd instanceof DOMElement ? $scd->getAttribute('Recipient') : '';

            if ($recipient === '' || $recipient !== $expectedRecipient) {
                throw SamlAssertionConditionsInvalid::recipientMismatch();
            }
        }
    }

    private function trustedResponse(SamlSignedXml $signed, DOMXPath $xpath): ?DOMElement
    {
        $response = $this->firstElement($xpath, '/samlp:Response');

        if (!$response instanceof DOMElement) {
            throw SamlSignatureInvalid::make();
        }

        if ($signed->validatedResponseId === null) {
            return null;
        }

        if ($response->getAttribute('ID') !== $signed->validatedResponseId) {
            throw SamlSignatureInvalid::make();
        }

        return $response;
    }

    private function trustedAssertion(SamlSignedXml $signed, DOMXPath $xpath, ?DOMElement $trustedResponse): DOMElement
    {
        if ($signed->validatedAssertionId !== null) {
            $assertion = $this->firstElement($xpath, '/samlp:Response/saml:Assertion');

            if ($assertion instanceof DOMElement && $assertion->getAttribute('ID') === $signed->validatedAssertionId) {
                return $assertion;
            }

            throw SamlSignatureInvalid::make();
        }

        if ($trustedResponse instanceof DOMElement) {
            $assertion = $this->firstElement($xpath, 'saml:Assertion', $trustedResponse);

            if ($assertion instanceof DOMElement) {
                return $assertion;
            }
        }

        throw SamlSignatureInvalid::make();
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
}
