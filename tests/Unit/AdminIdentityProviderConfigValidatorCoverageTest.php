<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Admin\DefaultIdentityProviderConfigValidator;
use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlSig;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

function trustedUrlPolicy(): UrlTrustPolicy
{
    return new class () implements UrlTrustPolicy {
        public function assertTrusted(string $url, string $field): void
        {
        }

        public function isTrusted(string $url): bool
        {
            return str_starts_with($url, 'https://');
        }
    };
}

function untrustedUrlPolicy(): UrlTrustPolicy
{
    return new class () implements UrlTrustPolicy {
        public function assertTrusted(string $url, string $field): void
        {
        }

        public function isTrusted(string $url): bool
        {
            return false;
        }
    };
}

it('accepts a complete trusted oidc configuration', function (): void {
    $validator = ValidatorFactory::make([], []);
    $service = new DefaultIdentityProviderConfigValidator(trustedUrlPolicy());

    $service->validate($validator, 'oidc', [
        'client_id' => 'client',
        'client_secret' => 'secret',
        'redirect_uri' => 'https://app.example.test/sso/tenant/connection/callback',
        'issuer' => 'https://idp.example.test',
        'discovery_enabled' => true,
        'userinfo_enabled' => false,
        'scope' => 'openid email',
        'response_type' => 'code',
        'endpoints' => [
            'authorization' => 'https://idp.example.test/authorize',
            'token' => 'https://idp.example.test/token',
            'jwks' => 'https://idp.example.test/jwks',
            'userinfo' => 'https://idp.example.test/userinfo',
        ],
    ]);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

it('reports oidc configuration shape and type errors', function (): void {
    $validator = ValidatorFactory::make([], []);
    $service = new DefaultIdentityProviderConfigValidator(trustedUrlPolicy());

    $service->validate($validator, 'oidc', [
        'client_id' => '',
        'redirect_uri' => 'not-a-url',
        'discovery_enabled' => 'yes',
        'userinfo_enabled' => 1,
        'scope' => '',
        'response_type' => '',
        'client_secret' => '',
    ]);

    expect($validator->errors()->has('config.client_id'))->toBeTrue()
        ->and($validator->errors()->has('config.redirect_uri'))->toBeTrue()
        ->and($validator->errors()->has('config'))->toBeTrue()
        ->and($validator->errors()->has('config.discovery_enabled'))->toBeTrue()
        ->and($validator->errors()->has('config.userinfo_enabled'))->toBeTrue()
        ->and($validator->errors()->has('config.scope'))->toBeTrue()
        ->and($validator->errors()->has('config.response_type'))->toBeTrue()
        ->and($validator->errors()->has('config.client_secret'))->toBeTrue();
});

it('requires complete endpoint groups and trusted discovery urls', function (): void {
    $validator = ValidatorFactory::make([], []);
    $service = new DefaultIdentityProviderConfigValidator(untrustedUrlPolicy());

    $service->validate($validator, 'oidc', [
        'client_id' => 'client',
        'redirect_uri' => 'https://app.example.test/sso/tenant/connection/callback',
        'discovery_url' => 'https://idp.example.test/.well-known/openid-configuration',
        'endpoints' => [
            'authorization' => 'https://idp.example.test/authorize',
            'token' => '',
            'jwks' => 'https://idp.example.test/jwks',
            'userinfo' => '',
        ],
    ]);

    expect($validator->errors()->has('config.discovery_url'))->toBeTrue()
        ->and($validator->errors()->has('config.endpoints.token'))->toBeTrue()
        ->and($validator->errors()->has('config.endpoints.userinfo'))->toBeTrue();
});

it('validates saml certificates metadata and attribute mapping', function (): void {
    $keys = SamlTestXmlSig::generateRsaCertPair();
    $validator = ValidatorFactory::make([], []);
    $service = new DefaultIdentityProviderConfigValidator(trustedUrlPolicy());

    $service->validate($validator, 'saml', [
        'saml_sso_url' => 'https://idp.example.test/sso',
        'saml_signing_certs_pem' => [$keys['public_cert_pem']],
        'metadata_url' => 'https://idp.example.test/metadata',
        'cert_thumbprint' => 'abc123',
        'attribute_mapping' => [
            'email' => ['mail', 'emailaddress'],
        ],
    ]);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

it('reports saml configuration errors for missing urls invalid certs and bad mappings', function (): void {
    $validator = ValidatorFactory::make([], []);
    $service = new DefaultIdentityProviderConfigValidator(untrustedUrlPolicy());

    $service->validate($validator, 'saml', [
        'saml_sso_url' => 'https://idp.example.test/sso',
        'saml_signing_certs_pem' => ['', 'not-a-cert'],
        'metadata_url' => '',
        'cert_thumbprint' => '',
        'attribute_mapping' => [
            '' => 'invalid',
            'groups' => [''],
        ],
    ]);

    expect($validator->errors()->has('config.saml_sso_url'))->toBeTrue()
        ->and($validator->errors()->has('config.saml_signing_certs_pem.0'))->toBeTrue()
        ->and($validator->errors()->has('config.saml_signing_certs_pem.1'))->toBeTrue()
        ->and($validator->errors()->has('config.metadata_url'))->toBeTrue()
        ->and($validator->errors()->has('config.cert_thumbprint'))->toBeTrue()
        ->and($validator->errors()->has('config.attribute_mapping'))->toBeTrue()
        ->and($validator->errors()->has('config.attribute_mapping.groups.0'))->toBeTrue();
});

it('requires redirect uri callback paths to end with /callback', function (): void {
    $validator = ValidatorFactory::make([], []);
    $service = new DefaultIdentityProviderConfigValidator(trustedUrlPolicy());

    $service->validate($validator, 'oidc', [
        'client_id' => 'client',
        'redirect_uri' => 'https://app.example.test/sso/tenant/connection/not-callback',
        'issuer' => 'https://idp.example.test',
    ]);

    expect($validator->errors()->get('config.redirect_uri'))->toContain(
        'The config.redirect_uri path should end with /callback to match the package OIDC callback route.',
    );
});
