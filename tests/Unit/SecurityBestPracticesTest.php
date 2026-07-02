<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\SafeRedirectValidator;
use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Exceptions\SamlAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Policies\GroupRequiredIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\GroupRequiredProvisioningPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

it('rejects unsafe redirect_to values at storage time', function () {
    $validator = new SafeRedirectValidator();
    $request = Request::create('https://app.example.test/sso/start', 'GET');

    expect($validator->sanitizeForStorage('https://evil.example.test/phish', $request))->toBeNull()
        ->and($validator->sanitizeForStorage('/dashboard', $request))->toBe('/dashboard')
        ->and($validator->sanitizeForStorage('//evil.example.test', $request))->toBeNull();
});

it('identifies ulid route keys', function () {
    $ulid = (string) Str::ulid();

    expect(TenantRouteKey::looksLikeUlid($ulid))->toBeTrue()
        ->and(TenantRouteKey::looksLikeUlid('1'))->toBeFalse()
        ->and(TenantRouteKey::isNumericId('42'))->toBeTrue();
});

it('assigns ulids when creating sso resources', function () {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'Acme']);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Okta',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Default',
        'enabled' => true,
        'guard' => 'web',
        'settings' => [],
    ]);

    expect($idp->ulid)->toHaveLength(26)
        ->and($connection->ulid)->toHaveLength(26);
});

it('denies linking when required groups are absent in example policy', function () {
    $tenant = new Tenant(['id' => 1, 'ulid' => 'tenant', 'name' => 'T']);
    $idp = new IdentityProvider(['id' => 1, 'tenant_id' => 1, 'name' => 'IdP', 'protocol' => 'oidc', 'enabled' => true]);
    $connection = new Connection([
        'id' => 1,
        'tenant_id' => 1,
        'identity_provider_id' => 1,
        'name' => 'Default',
        'enabled' => true,
        'settings' => [
            'required_link_groups' => ['staff'],
        ],
    ]);

    $policy = new GroupRequiredIdentityLinkPolicy();
    $user = new class () implements \Illuminate\Contracts\Auth\Authenticatable {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void
        {
        }

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    expect($policy->allows($tenant, $connection, $idp, $user, ['groups' => ['guest']]))->toBeFalse()
        ->and($policy->allows($tenant, $connection, $idp, $user, ['groups' => ['staff']]))->toBeTrue();
});

it('denies provisioning when required groups are absent in example policy', function () {
    $tenant = new Tenant(['id' => 1, 'ulid' => 'tenant', 'name' => 'T']);
    $idp = new IdentityProvider(['id' => 1, 'tenant_id' => 1, 'name' => 'IdP', 'protocol' => 'oidc', 'enabled' => true]);
    $connection = new Connection([
        'id' => 1,
        'tenant_id' => 1,
        'identity_provider_id' => 1,
        'name' => 'Default',
        'enabled' => true,
        'settings' => [
            'required_provision_groups' => ['employees'],
        ],
    ]);

    $policy = new GroupRequiredProvisioningPolicy();

    expect($policy->allows($tenant, $connection, $idp, ['groups' => ['guest']]))->toBeFalse()
        ->and($policy->allows($tenant, $connection, $idp, ['groups' => ['employees']]))->toBeTrue();
});

it('throws when saml authn request signing is enabled without keys', function () {
    config()->set('sso.saml.sp.sign_authn_requests', true);
    config()->set('sso.saml.sp.signing_private_key_pem', null);
    config()->set('sso.saml.sp.signing_certificate_pem', null);

    $driver = app(\CreativeCrafts\LaravelSso\Drivers\SamlDriver::class);

    $reflection = new ReflectionClass($driver);
    $method = $reflection->getMethod('maybeSignAuthnRequest');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($driver, '<samlp:AuthnRequest />'))
        ->toThrow(SamlAuthorizationRequestFailed::class, 'signing keys are not configured');
});
