<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Policies\DefaultIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\DefaultProvisioningPolicy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

function defaultPolicyTestUser(): Illuminate\Contracts\Auth\Authenticatable
{
    return new class () implements Illuminate\Contracts\Auth\Authenticatable {
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
}

function defaultPolicyFixtures(): array
{
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => ['client_id' => 'x', 'redirect_uri' => 'https://app.test/callback', 'issuer' => 'https://idp.test'],
    ]);
    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'identity_provider_id' => $idp->id,
        'name' => 'Conn',
        'enabled' => true,
        'settings' => [],
    ]);

    return [$tenant, $idp, $connection];
}

it('uses package defaults when connection settings omit overrides', function (): void {
    Config::set('sso.provisioning.enabled_by_default', true);
    Config::set('sso.linking.enabled_by_default', false);

    [$tenant, $idp, $connection] = defaultPolicyFixtures();

    expect((new DefaultProvisioningPolicy(app('config')))->allows($tenant, $connection, $idp, []))->toBeTrue()
        ->and((new DefaultIdentityLinkPolicy(app('config')))->allows($tenant, $connection, $idp, defaultPolicyTestUser(), []))->toBeFalse();
});

it('honors boolean connection setting overrides for provisioning and linking', function (): void {
    Config::set('sso.provisioning.enabled_by_default', false);
    Config::set('sso.linking.enabled_by_default', false);
    Config::set('sso.provisioning.connection_setting_key', 'allow_provisioning');
    Config::set('sso.linking.connection_setting_key', 'allow_identity_linking');

    [$tenant, $idp, $connection] = defaultPolicyFixtures();
    $connection->settings = [
        'allow_provisioning' => 'true',
        'allow_identity_linking' => 1,
    ];
    $connection->save();

    expect((new DefaultProvisioningPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, []))->toBeTrue()
        ->and((new DefaultIdentityLinkPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, defaultPolicyTestUser(), []))->toBeTrue();
});

it('ignores invalid boolean override values', function (): void {
    Config::set('sso.provisioning.connection_setting_key', 'allow_provisioning');
    Config::set('sso.provisioning.enabled_by_default', true);

    [$tenant, $idp, $connection] = defaultPolicyFixtures();
    $connection->settings = ['allow_provisioning' => 'maybe'];
    $connection->save();

    expect((new DefaultProvisioningPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, []))->toBeTrue();
});

it('honors explicit false connection setting overrides', function (): void {
    Config::set('sso.provisioning.connection_setting_key', 'allow_provisioning');
    Config::set('sso.provisioning.enabled_by_default', true);
    Config::set('sso.linking.connection_setting_key', 'allow_identity_linking');
    Config::set('sso.linking.enabled_by_default', true);

    [$tenant, $idp, $connection] = defaultPolicyFixtures();
    $connection->settings = [
        'allow_provisioning' => false,
        'allow_identity_linking' => 'false',
    ];
    $connection->save();

    expect((new DefaultProvisioningPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, []))->toBeFalse()
        ->and((new DefaultIdentityLinkPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, defaultPolicyTestUser(), []))->toBeFalse();
});

it('normalizes integer boolean overrides', function (): void {
    Config::set('sso.provisioning.connection_setting_key', 'allow_provisioning');
    Config::set('sso.provisioning.enabled_by_default', true);
    Config::set('sso.linking.connection_setting_key', 'allow_identity_linking');
    Config::set('sso.linking.enabled_by_default', true);

    [$tenant, $idp, $connection] = defaultPolicyFixtures();
    $connection->settings = [
        'allow_provisioning' => 0,
        'allow_identity_linking' => 0,
    ];
    $connection->save();

    expect((new DefaultProvisioningPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, []))->toBeFalse()
        ->and((new DefaultIdentityLinkPolicy(app('config')))->allows($tenant, $connection->fresh(), $idp, defaultPolicyTestUser(), []))->toBeFalse();
});

it('returns null override when the connection setting key is blank', function (): void {
    Config::set('sso.linking.connection_setting_key', '');
    Config::set('sso.linking.enabled_by_default', true);

    [$tenant, $idp, $connection] = defaultPolicyFixtures();

    expect((new DefaultIdentityLinkPolicy(app('config')))->allows($tenant, $connection, $idp, defaultPolicyTestUser(), []))->toBeTrue();
});
