<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Admin\DefaultIdentityProviderConfigValidator;
use CreativeCrafts\LaravelSso\Contracts\Admin\IdentityProviderConfigValidator;
use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\IdentityProviderStoreRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;

it('validates trusted IdP URLs through injected policy', function (): void {
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

it('delegates store request config validation through the admin validator contract', function (): void {
    $calls = [];

    app()->bind(IdentityProviderConfigValidator::class, static function () use (&$calls): IdentityProviderConfigValidator {
        return new class ($calls) implements IdentityProviderConfigValidator {
            /** @param array<int, array{protocol: string, config: array<string, mixed>}> $calls */
            public function __construct(private array &$calls)
            {
            }

            public function validate(Validator $validator, string $protocol, array $config): void
            {
                $this->calls[] = [
                    'protocol' => $protocol,
                    'config' => $config,
                ];

                $validator->errors()->add('config.discovery_url', 'Injected validator was called.');
            }
        };
    });

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
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]['protocol'])->toBe('oidc')
        ->and($calls[0]['config']['client_id'])->toBe('client-one');
});
