<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class IdentityProviderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
          'name' => ['required', 'string', 'max:255'],
          'protocol' => ['required', 'string', 'in:oidc,saml'],
          'enabled' => ['sometimes', 'boolean'],
          'config' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $protocolRaw = $this->input('protocol');
            $configRaw = $this->input('config');

            $protocol = is_string($protocolRaw) ? $protocolRaw : '';

            /** @var array<string, mixed> $config */
            $config = is_array($configRaw) ? $this->stringKeyedArray($configRaw) : [];

            if ($protocol === 'oidc') {
                $this->validateOidcConfig($validator, $config);
            }

            if ($protocol === 'saml') {
                $this->validateSamlConfig($validator, $config);
            }
        });
    }

    /**
     * Convert an arbitrary PHP array into an associative array with string keys.
     *
     * @param array<mixed> $input
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $input): array
    {
        return array_filter($input, static function ($key) {
            return is_string($key);
        }, ARRAY_FILTER_USE_KEY);
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
        }

        $hasEndpoints = is_array($config['endpoints'] ?? null);
        $hasIssuer = is_string($config['issuer'] ?? null) && $config['issuer'] !== '';
        $hasDiscoveryUrl = is_string($config['discovery_url'] ?? null) && $config['discovery_url'] !== '';

        if (!$hasEndpoints && !$hasIssuer && !$hasDiscoveryUrl) {
            $validator->errors()->add('config', 'OIDC config must include endpoints OR issuer OR discovery_url.');
        }

        $endpoints = $config['endpoints'] ?? null;
        if (is_array($endpoints)) {
            foreach (['authorization', 'token', 'jwks'] as $key) {
                $value = $endpoints[$key] ?? null;
                if (!is_string($value) || $value === '') {
                    $validator->errors()->add('config.endpoints.' . $key, "The config.endpoints.{$key} field is required when endpoints are provided.");
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
            }
        }
    }
}
