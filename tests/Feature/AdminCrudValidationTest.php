<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\UiApiTestCase;

uses(UiApiTestCase::class);

it('rejects creating an oidc idp with missing required config', function () {
    $tenant = Tenant::query()->create([
      'ulid' => 'tenant_01',
      'name' => 'Tenant 01',
      'metadata' => [],
    ]);

    $resp = $this->postJson('/admin/sso/tenants/' . $tenant->ulid . '/idps', [
      'name' => 'Example OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
          // missing client_id + redirect_uri
        'issuer' => 'https://idp.example.test',
      ],
    ]);

    $resp->assertStatus(422);
    $resp->assertJsonValidationErrors(['config.client_id', 'config.redirect_uri']);
});

it('rejects creating a saml idp without signing certs', function () {
    $tenant = Tenant::query()->create([
      'ulid' => 'tenant_02',
      'name' => 'Tenant 02',
      'metadata' => [],
    ]);

    $resp = $this->postJson('/admin/sso/tenants/' . $tenant->ulid . '/idps', [
      'name' => 'Example SAML',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [],
    ]);

    $resp->assertStatus(422);
    $resp->assertJsonValidationErrors(['config.saml_signing_certs_pem']);
});

it('enforces tenant scoping when updating identity providers', function () {
    $tenantA = Tenant::query()->create([
      'ulid' => 'tenant_a',
      'name' => 'Tenant A',
      'metadata' => [],
    ]);

    $tenantB = Tenant::query()->create([
      'ulid' => 'tenant_b',
      'name' => 'Tenant B',
      'metadata' => [],
    ]);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenantA->id,
      'name' => 'A OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'client_id' => 'abc',
        'redirect_uri' => 'https://app.example.test/callback',
        'issuer' => 'https://idp.example.test',
      ],
    ]);

    $resp = $this->putJson('/admin/sso/tenants/' . $tenantB->ulid . '/idps/' . $idp->id, [
      'name' => 'Should not work',
    ]);

    $resp->assertStatus(404);
});

it('rejects creating a connection pointing to an idp in another tenant', function () {
    $tenantA = Tenant::query()->create([
      'ulid' => 'tenant_ca',
      'name' => 'Tenant CA',
      'metadata' => [],
    ]);

    $tenantB = Tenant::query()->create([
      'ulid' => 'tenant_cb',
      'name' => 'Tenant CB',
      'metadata' => [],
    ]);

    $idpB = IdentityProvider::query()->create([
      'tenant_id' => $tenantB->id,
      'name' => 'B OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'client_id' => 'b',
        'redirect_uri' => 'https://app.example.test/callback',
        'issuer' => 'https://idp.example.test',
      ],
    ]);

    $resp = $this->postJson('/admin/sso/tenants/' . $tenantA->ulid . '/connections', [
      'identity_provider_id' => $idpB->id,
      'name' => 'Conn',
      'enabled' => true,
      'settings' => [],
    ]);

    $resp->assertStatus(422);
    $resp->assertJsonValidationErrors(['identity_provider_id']);
});
