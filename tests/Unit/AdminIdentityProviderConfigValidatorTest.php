<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Admin\DefaultIdentityProviderConfigValidator;
use CreativeCrafts\LaravelSso\Contracts\Admin\IdentityProviderConfigValidator;
use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\IdentityProviderStoreRequest;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\IdentityProviderUpdateRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;

it('validates OIDC trusted IdP URLs through injected policy', function (): void {
    $service = new DefaultIdentityProviderConfigValidator(new class () implements UrlTrustPolicy {
        public function assertTrusted(string $url, string $field): void
        {
            throw UnsafeIdpUrl::forField($field, 'not trusted');
        }

        public function isTrusted(string $url): bool
        {
            return false;
        }
    });

    $validator = ValidatorFactory::make([], []);

    $service->validate($validator, 'oidc', [
        'client_id' => 'client-one',
        'redirect_uri' => 'https://app.example.test/sso/callback',
        'discovery_url' => 'https://idp.example.test/.well-known/openid-configuration',
    ]);

    expect($validator->errors()->get('config.discovery_url'))->toBe([
        'The config.discovery_url field must be a trusted https URL.',
    ]);
});

it('validates SAML trusted IdP URLs through injected policy', function (): void {
    $service = new DefaultIdentityProviderConfigValidator(new class () implements UrlTrustPolicy {
        public function assertTrusted(string $url, string $field): void
        {
            throw UnsafeIdpUrl::forField($field, 'not trusted');
        }

        public function isTrusted(string $url): bool
        {
            return false;
        }
    });

    $validator = ValidatorFactory::make([], []);

    $service->validate($validator, 'saml', [
        'saml_sso_url' => 'https://idp.example.test/sso',
        'saml_signing_certs_pem' => ['not-a-valid-certificate'],
    ]);

    expect($validator->errors()->get('config.saml_sso_url'))->toBe([
        'The config.saml_sso_url field must be a trusted https URL.',
    ]);
});

it('delegates store request config validation through the admin validator contract', function (): void {
    $recorder = adminValidationRecorder();

    bindAdminValidationRecorder($recorder);

    $request = IdentityProviderStoreRequest::create('/admin/sso/tenants/tenant-one/idps', 'POST', [
        'name' => 'Example IdP',
        'protocol' => 'oidc',
        'config' => [
            'client_id' => 'client-one',
            'redirect_uri' => 'https://app.example.test/sso/callback',
            'discovery_url' => 'https://idp.example.test/.well-known/openid-configuration',
        ],
    ]);
    $request->setContainer(app());

    $validator = ValidatorFactory::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('config.discovery_url'))->toBe(['Injected validator was called.'])
        ->and($recorder->calls)->toHaveCount(1)
        ->and($recorder->calls[0]['protocol'])->toBe('oidc')
        ->and($recorder->calls[0]['config']['client_id'])->toBe('client-one');
});

it('delegates update request config validation through the admin validator contract', function (): void {
    $recorder = adminValidationRecorder();

    bindAdminValidationRecorder($recorder);

    $request = IdentityProviderUpdateRequest::create('/admin/sso/tenants/tenant-one/idps/1', 'PATCH', [
        'protocol' => 'saml',
        'config' => [
            'saml_sso_url' => 'https://idp.example.test/sso',
            'saml_signing_certs_pem' => ['not-a-valid-certificate'],
        ],
    ]);
    $request->setContainer(app());

    $validator = ValidatorFactory::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('config.discovery_url'))->toBe(['Injected validator was called.'])
        ->and($recorder->calls)->toHaveCount(1)
        ->and($recorder->calls[0]['protocol'])->toBe('saml')
        ->and($recorder->calls[0]['config']['saml_sso_url'])->toBe('https://idp.example.test/sso');
});

function adminValidationRecorder(): object
{
    return new class () {
        /** @var array<int, array{protocol: string, config: array<string, mixed>}> */
        public array $calls = [];
    };
}

function bindAdminValidationRecorder(object $recorder): void
{
    app()->bind(IdentityProviderConfigValidator::class, static function () use ($recorder): IdentityProviderConfigValidator {
        return new class ($recorder) implements IdentityProviderConfigValidator {
            public function __construct(private readonly object $recorder)
            {
            }

            public function validate(Validator $validator, string $protocol, array $config): void
            {
                /** @var object{calls: array<int, array{protocol: string, config: array<string, mixed>}>} $recorder */
                $recorder = $this->recorder;
                $recorder->calls[] = [
                    'protocol' => $protocol,
                    'config' => $config,
                ];

                $validator->errors()->add('config.discovery_url', 'Injected validator was called.');
            }
        };
    });
}
