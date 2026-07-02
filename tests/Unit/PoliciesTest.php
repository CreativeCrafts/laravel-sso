<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Policies\AllowIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\AllowProvisioningPolicy;
use CreativeCrafts\LaravelSso\Policies\GroupRequiredIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\GroupRequiredProvisioningPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

function policyTestUser(): Authenticatable
{
    return new class () implements Authenticatable {
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

it('allows provisioning and linking through explicit allow policies', function (): void {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => ['client_id' => 'x', 'redirect_uri' => 'https://app.test/callback', 'issuer' => 'https://idp.test'],
    ]);
    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Conn',
        'enabled' => true,
        'settings' => [],
    ]);

    $claims = ['email' => 'user@example.test'];

    expect((new AllowProvisioningPolicy())->allows($tenant, $connection, $idp, $claims))->toBeTrue()
        ->and((new AllowIdentityLinkPolicy())->allows($tenant, $connection, $idp, policyTestUser(), $claims))->toBeTrue();
});

it('denies group required policies when required groups are not configured', function (): void {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => ['client_id' => 'x', 'redirect_uri' => 'https://app.test/callback', 'issuer' => 'https://idp.test'],
    ]);
    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Conn',
        'enabled' => true,
        'settings' => [],
    ]);

    $claims = ['groups' => ['staff']];

    expect((new GroupRequiredProvisioningPolicy())->allows($tenant, $connection, $idp, $claims))->toBeFalse()
        ->and((new GroupRequiredIdentityLinkPolicy())->allows($tenant, $connection, $idp, policyTestUser(), $claims))->toBeFalse();
});

it('allows group required policies when a configured group matches claims', function (): void {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'T']);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => ['client_id' => 'x', 'redirect_uri' => 'https://app.test/callback', 'issuer' => 'https://idp.test'],
    ]);
    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => $idp->id,
        'name' => 'Conn',
        'enabled' => true,
        'settings' => [
            'required_provision_groups' => ['staff'],
            'required_link_groups' => ['staff'],
        ],
    ]);

    $claims = ['groups' => ['staff']];

    expect((new GroupRequiredProvisioningPolicy())->allows($tenant, $connection, $idp, $claims))->toBeTrue()
        ->and((new GroupRequiredIdentityLinkPolicy())->allows($tenant, $connection, $idp, policyTestUser(), $claims))->toBeTrue();
});
