<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Exceptions\SamlAcsRequestInvalid;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SamlAcsController
{
    public function __construct(
        private SamlSignatureValidator $signatures,
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

        if (!is_array($rawCerts)) {
            $rawCerts = [];
        }

        $signingCertsPem = [];

        foreach ($rawCerts as $cert) {
            if (is_string($cert) && $cert !== '') {
                $signingCertsPem[] = $cert;
            }
        }

        $this->signatures->validate($xml, $signingCertsPem);

        return response('', 204);
    }
}
