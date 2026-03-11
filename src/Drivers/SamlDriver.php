<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Drivers;

use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Exceptions\SamlAcsRequestInvalid;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use RuntimeException;

final readonly class SamlDriver implements SsoDriver
{
    public function __construct(
        private SamlSignatureValidator $signatures,
        private SamlAssertionConditionsValidator $conditions,
        private SamlClaimsNormalizer $claimsNormalizer,
    ) {
    }

    public function protocol(): string
    {
        return 'saml';
    }

    public function start(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverStartResult
    {
        $identityProvider = $connection->identityProvider;

        if (!$identityProvider instanceof IdentityProvider) {
            throw new RuntimeException('SAML identity provider is missing on connection.');
        }

        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $ssoUrl = $config['saml_sso_url'] ?? null;
        if (!is_string($ssoUrl) || $ssoUrl === '') {
            throw new RuntimeException('SAML identity provider is missing config.saml_sso_url.');
        }

        if ($attempt->state === '') {
            throw new RuntimeException('SAML auth attempt is missing state.');
        }

        $acsUrl = route('sso.saml.acs', [
            'tenant' => $tenant->ulid,
            'idp' => (string) $connection->id,
        ], true);

        $metadataUrl = route('sso.saml.metadata', [
            'tenant' => $tenant->ulid,
            'idp' => (string) $connection->id,
        ], true);

        $configuredEntityId = config('sso.saml.sp.entity_id');
        $issuer = is_string($configuredEntityId) && $configuredEntityId !== '' ? $configuredEntityId : $metadataUrl;

        $issueInstant = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $requestId = '_' . bin2hex(random_bytes(16));

        $authnRequestXml = $this->buildAuthnRequestXml(
            requestId: $requestId,
            issueInstant: $issueInstant,
            destination: $ssoUrl,
            assertionConsumerServiceUrl: $acsUrl,
            issuer: $issuer,
        );

        $deflated = gzdeflate($authnRequestXml, 9);

        if (!is_string($deflated) || $deflated === '') {
            throw new RuntimeException('Unable to compress SAML AuthnRequest.');
        }

        $query = http_build_query([
            'SAMLRequest' => base64_encode($deflated),
            'RelayState' => $attempt->state,
        ], '', '&', PHP_QUERY_RFC3986);

        $separator = str_contains($ssoUrl, '?') ? '&' : '?';

        return new DriverStartResult(
            redirectUrl: $ssoUrl . $separator . $query,
            context: [
                'binding' => 'HTTP-Redirect',
                'request_id' => $requestId,
            ],
        );
    }

    public function handleCallback(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverCallbackResult
    {
        $encoded = $request->input('SAMLResponse');

        if (!is_string($encoded) || $encoded === '') {
            throw SamlAcsRequestInvalid::missingResponse();
        }

        $xml = base64_decode($encoded, true);

        if (!is_string($xml) || $xml === '') {
            throw SamlAcsRequestInvalid::invalidBase64();
        }

        $identityProvider = $connection->identityProvider;

        if (!$identityProvider instanceof IdentityProvider) {
            throw new RuntimeException('SAML identity provider is missing on connection.');
        }

        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $rawCerts = $config['saml_signing_certs_pem'] ?? [];
        $signingCertsPem = [];

        if (is_array($rawCerts)) {
            foreach ($rawCerts as $cert) {
                if (is_string($cert) && $cert !== '') {
                    $signingCertsPem[] = $cert;
                }
            }
        }

        $signed = $this->signatures->validate($xml, $signingCertsPem);

        $acsUrl = route('sso.saml.acs', [
            'tenant' => $tenant->ulid,
            'idp' => (string) $connection->id,
        ], true);

        $metadataUrl = route('sso.saml.metadata', [
            'tenant' => $tenant->ulid,
            'idp' => (string) $connection->id,
        ], true);

        $entityId = config('sso.saml.sp.entity_id');
        $expectedAudience = is_string($entityId) && $entityId !== '' ? $entityId : $metadataUrl;

        $this->conditions->validate(
            signed: $signed,
            expectedAudience: $expectedAudience,
            expectedRecipient: $acsUrl,
            expectedDestination: $acsUrl,
            clockSkewSeconds: Config::integer('sso.saml.clock_skew_seconds', 60),
            requireAudience: (bool) config('sso.saml.require_audience', true),
            requireRecipient: (bool) config('sso.saml.require_recipient', true),
            requireDestination: (bool) config('sso.saml.require_destination', true),
        );

        $canonicalClaims = $this->claimsNormalizer->normalize($xml);

        return new DriverCallbackResult(
            authenticated: true,
            canonicalClaims: $canonicalClaims,
            subject: $canonicalClaims->subject,
            email: $canonicalClaims->email,
            displayName: $canonicalClaims->displayName,
            claims: $canonicalClaims->toArray(),
            context: [
                'response_signature_valid' => $signed->validatedResponseSignature,
                'assertion_signature_valid' => $signed->validatedAssertionSignature,
            ],
            error: null,
        );
    }

    private function buildAuthnRequestXml(
        string $requestId,
        string $issueInstant,
        string $destination,
        string $assertionConsumerServiceUrl,
        string $issuer,
    ): string {
        $requestId = $this->xmlEscape($requestId);
        $issueInstant = $this->xmlEscape($issueInstant);
        $destination = $this->xmlEscape($destination);
        $assertionConsumerServiceUrl = $this->xmlEscape($assertionConsumerServiceUrl);
        $issuer = $this->xmlEscape($issuer);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$requestId}"
    Version="2.0"
    IssueInstant="{$issueInstant}"
    Destination="{$destination}"
    AssertionConsumerServiceURL="{$assertionConsumerServiceUrl}"
    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$issuer}</saml:Issuer>
    <samlp:NameIDPolicy
        AllowCreate="true"
        Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified" />
</samlp:AuthnRequest>
XML;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
