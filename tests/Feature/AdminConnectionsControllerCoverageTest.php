<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\UiApiTestCase;
use Illuminate\Support\Str;

uses(UiApiTestCase::class);

it('returns not found for unknown admin connection routes', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant',
    ]);

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/01JUNKNOWN0000000000000000')
        ->assertNotFound();

    $this->deleteJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/01JUNKNOWN0000000000000000')
        ->assertNotFound();
});

it('returns not found when creating a connection for an unknown tenant', function (): void {
    $this->postJson('/admin/sso/tenants/01JUNKNOWN0000000000000000/connections', [
        'name' => 'Conn',
        'identity_provider_id' => 1,
        'enabled' => true,
    ])->assertNotFound();
});

it('returns validation errors when storing a connection with an invalid identity provider reference', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant',
    ]);

    $this->postJson('/admin/sso/tenants/' . $tenant->ulid . '/connections', [
        'name' => 'Conn',
        'identity_provider_id' => 'not-valid',
        'enabled' => true,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['identity_provider_id']);
});

it('returns unprocessable entity when the identity provider belongs to another tenant', function (): void {
    $tenantA = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'A']);
    $tenantB = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'B']);
    $idpB = IdentityProvider::query()->create([
        'tenant_id' => $tenantB->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'B IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'client_id' => 'client',
            'redirect_uri' => 'https://app.test/callback',
            'issuer' => 'https://idp.test',
        ],
    ]);

    $this->postJson('/admin/sso/tenants/' . $tenantA->ulid . '/connections', [
        'name' => 'Conn',
        'identity_provider_id' => $idpB->ulid,
        'enabled' => true,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['identity_provider_id']);
});

it('shows a connection by ulid through the admin api', function (): void {
    $tenant = Tenant::query()->create(['ulid' => (string) Str::ulid(), 'name' => 'Tenant']);
    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'ulid' => (string) Str::ulid(),
        'name' => 'IdP',
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

    $this->getJson('/admin/sso/tenants/' . $tenant->ulid . '/connections/' . $connection->ulid)
        ->assertOk()
        ->assertJsonFragment(['ulid' => $connection->ulid, 'name' => 'Conn']);
});
