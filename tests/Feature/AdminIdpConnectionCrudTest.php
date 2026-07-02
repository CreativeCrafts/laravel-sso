<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\SamlTestXmlSig;
use CreativeCrafts\LaravelSso\Tests\Support\UiApiTestCase;
use Illuminate\Support\Str;

uses(UiApiTestCase::class);

it('creates oidc idp and connection through admin api', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Acme',
        'metadata' => ['domain' => 'acme.example.test'],
    ]);

    $idpResponse = $this->postJson('/admin/sso/tenants/' . $tenant->ulid . '/idps', [
        'name' => 'Acme OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'client_id' => 'client-123',
            'client_secret' => 'secret',
            'redirect_uri' => 'https://app.example.test/sso/' . $tenant->ulid . '/conn/callback',
            'issuer' => 'https://idp.example.test',
        ],
    ]);

    $idpResponse->assertCreated();
    $idpUlid = (string) $idpResponse->json('data.ulid');
    expect($idpUlid)->not->toBe('');

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/idps/' . $idpUlid)
        ->assertOk()
        ->assertJsonPath('data.config.client_secret', '[redacted]');

    $connectionResponse = $this->postJson('/admin/sso/tenants/' . $tenant->ulid . '/connections', [
        'name' => 'Default',
        'identity_provider_id' => $idpUlid,
        'enabled' => true,
        'settings' => ['allow_provisioning' => false],
    ]);

    $connectionResponse->assertCreated();
    $connectionUlid = (string) $connectionResponse->json('data.ulid');

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/' . $connectionUlid)
        ->assertOk()
        ->assertJsonFragment(['ulid' => $connectionUlid, 'name' => 'Default']);

    $this->deleteJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/' . $connectionUlid)
        ->assertNoContent();
});

it('creates saml idp through admin api', function (): void {
    $keys = SamlTestXmlSig::generateRsaCertPair();

    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'SAML Tenant',
    ]);

    $response = $this->postJson('/admin/sso/tenants/' . $tenant->ulid . '/idps', [
        'name' => 'Acme SAML',
        'protocol' => 'saml',
        'enabled' => true,
        'config' => [
            'saml_sso_url' => 'https://idp.example.test/sso',
            'saml_signing_certs_pem' => [$keys['public_cert_pem']],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonFragment(['protocol' => 'saml']);
});

it('lists updates shows and deletes identity providers through the admin api', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'IdP Tenant',
    ]);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'Original IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'client_id' => 'client',
            'redirect_uri' => 'https://app.test/sso/t/c/callback',
            'issuer' => 'https://idp.test',
        ],
    ]);

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/idps')
        ->assertOk()
        ->assertJsonFragment(['ulid' => $idp->ulid, 'name' => 'Original IdP']);

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/idps/' . $idp->ulid)
        ->assertOk()
        ->assertJsonFragment(['name' => 'Original IdP']);

    $this->patchJson('/admin/sso/tenants/' . $tenant->ulid . '/idps/' . $idp->ulid, [
        'name' => 'Updated IdP',
        'enabled' => false,
    ])->assertOk()
        ->assertJsonFragment(['name' => 'Updated IdP', 'enabled' => false]);

    $this->deleteJson('/admin/sso/tenants/' . $tenant->ulid . '/idps/' . $idp->ulid)
        ->assertNoContent();
});

it('lists and updates connections through the admin api', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'List Tenant',
    ]);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'client_id' => 'client',
            'redirect_uri' => 'https://app.test/callback',
            'issuer' => 'https://idp.test',
        ],
    ]);
    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'identity_provider_id' => $idp->id,
        'name' => 'Original',
        'enabled' => true,
        'settings' => [],
    ]);

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/connections')
        ->assertOk()
        ->assertJsonFragment(['ulid' => $connection->ulid, 'name' => 'Original']);

    $this->patchJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/' . $connection->ulid, [
        'name' => 'Updated',
        'enabled' => false,
        'settings' => ['allow_provisioning' => true],
    ])->assertOk()
        ->assertJsonFragment(['name' => 'Updated', 'enabled' => false]);

    $this->patchJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/' . $connection->ulid, [
        'identity_provider_id' => $idp->ulid,
    ])->assertOk()
        ->assertJsonFragment(['identity_provider_id' => $idp->id]);
});

it('returns validation errors when updating a connection with an invalid identity provider reference', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Validation Tenant',
    ]);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'client_id' => 'client',
            'redirect_uri' => 'https://app.test/callback',
            'issuer' => 'https://idp.test',
        ],
    ]);
    $connection = Connection::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'identity_provider_id' => $idp->id,
        'name' => 'Conn',
        'enabled' => true,
        'settings' => [],
    ]);

    $this->patchJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/' . $connection->ulid, [
        'identity_provider_id' => 'not-a-valid-reference',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['identity_provider_id']);
});
