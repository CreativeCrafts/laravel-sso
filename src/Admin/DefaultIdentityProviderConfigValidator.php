<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Admin;

use CreativeCrafts\LaravelSso\Contracts\Admin\IdentityProviderConfigValidator;
use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use Illuminate\Validation\Validator;

final readonly class DefaultIdentityProviderConfigValidator implements IdentityProviderConfigValidator
{
    public function __construct(private UrlTrustPolicy $urls)
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function validate(Validator $validator, string $protocol, array $config): void
    {
        if ($protocol === 'oidc') {
            $this->validateOidcConfig($validator, $config);
        }

        if ($protocol === 'saml') {
            $this->validateSamlConfig($validator, $config);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateOidcConfig(Validator $validator, array $config): void
    {
        $clientId = $config['client_id'] ?? null;
        if (!is_string($clientId) || $clientId === '') {
            $validator->errors()->add('config.client_id', 'The config.client_id field is required for oidc.');
        }

        $redirectUri = $config['redirect_uri'] ?? null;
        if (!is_string($redirectUri) || $redirectUri === '') {
            $validator->errors()->add('config.redirect_uri', 'The config.redirect_uri field is required for oidc.');
        } elseif (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
            $validator->errors()->add('config.redirect_uri', 'The config.redirect_uri field must be a valid URL.');
        }

        $hasEndpoints = is_array($config['endpoints'] ?? null);
        $hasIssuer = is_string($config['issuer'] ?? null) && $config['issuer'] !== '';
        $hasDiscoveryUrl = is_string($config['discovery_url'] ?? null) && $config['discovery_url'] !== '';

        if (!$hasEndpoints && !$hasIssuer && !$hasDiscoveryUrl) {
            $validator->errors()->add('config', 'OIDC config must include endpoints OR issuer OR discovery_url.');
        }

        $issuer = $config['issuer'] ?? null;
        if (is_string($issuer) && $issuer !== '') {
            $this->validateTrustedUrl($validator, rtrim($issuer, '/') . '/.well-known/openid-configuration', 'config.issuer');
        }

        $discoveryUrl = $config['discovery_url'] ?? null;
        if (is_string($discoveryUrl) && $discoveryUrl !== '') {
            $this->validateTrustedUrl($validator, $discoveryUrl, 'config.discovery_url');
        }

        $endpoints = $config['endpoints'] ?? null;
        if (is_array($endpoints)) {
            foreach (['authorization', 'token', 'jwks'] as $key) {
                $value = $endpoints[$key] ?? null;

                if (!is_string($value) || $value === '') {
                    $validator->errors()->add('config.endpoints.' . $key, "The config.endpoints.{$key} field is required when endpoints are provided.");
                    continue;
                }

                $this->validateTrustedUrl($validator, $value, 'config.endpoints.' . $key);
            }

            $userinfo = $endpoints['userinfo'] ?? null;
            if ($userinfo !== null) {
                if (!is_string($userinfo) || $userinfo === '') {
                    $validator->errors()->add('config.endpoints.userinfo', 'The config.endpoints.userinfo field must be a non-empty string when provided.');
                } else {
                    $this->validateTrustedUrl($validator, $userinfo, 'config.endpoints.userinfo');
                }
            }
        }

        $discoveryEnabled = $config['discovery_enabled'] ?? null;
        if ($discoveryEnabled !== null && !is_bool($discoveryEnabled)) {
            $validator->errors()->add('config.discovery_enabled', 'The config.discovery_enabled field must be boolean.');
        }

        $userinfoEnabled = $config['userinfo_enabled'] ?? null;
        if ($userinfoEnabled !== null && !is_bool($userinfoEnabled)) {
            $validator->errors()->add('config.userinfo_enabled', 'The config.userinfo_enabled field must be boolean.');
        }

        $scope = $config['scope'] ?? null;
        if ($scope !== null && (!is_string($scope) || $scope === '')) {
            $validator->errors()->add('config.scope', 'The config.scope field must be a non-empty string.');
        }

        $responseType = $config['response_type'] ?? null;
        if ($responseType !== null && (!is_string($responseType) || $responseType === '')) {
            $validator->errors()->add('config.response_type', 'The config.response_type field must be a non-empty string.');
        }

        $clientSecret = $config['client_secret'] ?? null;
        if ($clientSecret !== null && (!is_string($clientSecret) || $clientSecret === '')) {
            $validator->errors()->add('config.client_secret', 'The config.client_secret field must be a non-empty string when provided.');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateSamlConfig(Validator $validator, array $config): void
    {
        $ssoUrl = $config['saml_sso_url'] ?? null;

        if (!is_string($ssoUrl) || $ssoUrl === '') {
            $validator->errors()->add('config.saml_sso_url', 'The config.saml_sso_url field is required for saml.');
        } else {
            $this->validateTrustedUrl($validator, $ssoUrl, 'config.saml_sso_url');
        }

        $certs = $config['saml_signing_certs_pem'] ?? null;

        if (!is_array($certs) || $certs === []) {
            $validator->errors()->add(
                'config.saml_signing_certs_pem',
                'The config.saml_signing_certs_pem field is required for saml and must be a non-empty array.',
            );

            return;
        }

        foreach ($certs as $i => $cert) {
            if (!is_string($cert) || $cert === '') {
                $validator->errors()->add('config.saml_signing_certs_pem.' . $i, 'Each certificate must be a non-empty string.');
                continue;
            }

            if (@openssl_x509_read($cert) === false) {
                $validator->errors()->add('config.saml_signing_certs_pem.' . $i, 'Each certificate must be a valid PEM encoded X.509 certificate.');
            }
        }

        $metadataUrl = $config['metadata_url'] ?? null;
        if ($metadataUrl !== null) {
            if (!is_string($metadataUrl) || $metadataUrl === '') {
                $validator->errors()->add('config.metadata_url', 'The config.metadata_url field must be a non-empty string when provided.');
            } else {
                $this->validateTrustedUrl($validator, $metadataUrl, 'config.metadata_url');
            }
        }

        $certThumbprint = $config['cert_thumbprint'] ?? null;
        if ($certThumbprint !== null && (!is_string($certThumbprint) || $certThumbprint === '')) {
            $validator->errors()->add('config.cert_thumbprint', 'The config.cert_thumbprint field must be a non-empty string when provided.');
        }

        $attributeMapping = $config['attribute_mapping'] ?? null;
        if ($attributeMapping !== null) {
            if (!is_array($attributeMapping)) {
                $validator->errors()->add('config.attribute_mapping', 'The config.attribute_mapping field must be an array when provided.');
                return;
            }

            foreach ($attributeMapping as $key => $mappingValues) {
                if (!is_string($key) || $key === '') {
                    $validator->errors()->add('config.attribute_mapping', 'Attribute mapping keys must be non-empty strings.');
                    continue;
                }

                if (!is_array($mappingValues)) {
                    $validator->errors()->add('config.attribute_mapping.' . $key, 'Each attribute mapping value must be an array of strings.');
                    continue;
                }

                foreach ($mappingValues as $index => $mappingValue) {
                    if (!is_string($mappingValue) || trim($mappingValue) === '') {
                        $validator->errors()->add('config.attribute_mapping.' . $key . '.' . $index, 'Each mapped attribute name must be a non-empty string.');
                    }
                }
            }
        }
    }

    private function validateTrustedUrl(Validator $validator, string $url, string $field): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $validator->errors()->add($field, "The {$field} field must be a valid URL.");
            return;
        }

        if (!$this->urls->isTrusted($url)) {
            $validator->errors()->add($field, "The {$field} field must be a trusted https URL.");
        }
    }
}
