<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Exceptions\SamlAcsRequestInvalid;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final readonly class SamlAcsController
{
    public function __construct(
        private SamlSignatureValidator $signatures,
        private SamlAssertionConditionsValidator $conditions,
    ) {
    }

    public function __invoke(Request $request, string $tenant, string $idp): Response
    {
        $encoded = $request->input('SAMLResponse');

        if (!is_string($encoded) || $encoded === '') {
            throw SamlAcsRequestInvalid::missingResponse();
        }

        $xml = base64_decode($encoded, true);

        if (!is_string($xml) || $xml === '') {
            throw SamlAcsRequestInvalid::invalidBase64();
        }

        $tenantModel = Tenant::query()
          ->where('ulid', $tenant)
          ->firstOrFail();

        $identityProvider = $tenantModel
          ->identityProviders()
          ->whereKey((int)$idp)
          ->firstOrFail();

        /** @var array<string, mixed> $idpConfig */
        $idpConfig = is_array($identityProvider->config) ? $identityProvider->config : [];

        $rawCerts = $idpConfig['saml_signing_certs_pem'] ?? [];
        $certs = is_array($rawCerts) ? $rawCerts : [];

        $signingCertsPem = [];
        foreach ($certs as $cert) {
            if (is_string($cert) && $cert !== '') {
                $signingCertsPem[] = $cert;
            }
        }

        $signed = $this->signatures->validate($xml, $signingCertsPem);

        $acsUrl = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
        $metadataUrl = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

        $entityId = config('sso.saml.sp.entity_id');
        $expectedAudience = is_string($entityId) && $entityId !== '' ? $entityId : $metadataUrl;

        $this->conditions->validate(
            signed: $signed,
            expectedAudience: $expectedAudience,
            expectedRecipient: $acsUrl,
            expectedDestination: $acsUrl,
            clockSkewSeconds: Config::integer('sso.saml.clock_skew_seconds', 60),
            requireAudience: (bool)config('sso.saml.require_audience', true),
            requireRecipient: (bool)config('sso.saml.require_recipient', true),
            requireDestination: (bool)config('sso.saml.require_destination', true),
        );

        return response('', 204);
    }
}
