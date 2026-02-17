<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentIdentityProviderRepository;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('scopes identity provider CRUD by tenant', function () {
    $repo = new EloquentIdentityProviderRepository();

    $tenantA = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);
    $tenantB = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'B']);

    $idpA = $repo->create($tenantA, [
      'tenant_id' => 999,
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => ['issuer' => 'https://issuer-a.test'],
    ]);

    $idpB = $repo->create($tenantB, [
      'name' => 'Azure',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => ['entity_id' => 'urn:azure-b'],
    ]);

    expect($idpA->tenant_id)
      ->toBe($tenantA->id)
      ->and($idpB->tenant_id)->toBe($tenantB->id)
      ->and($repo->listForTenant($tenantA))->toHaveCount(1)
      ->and($repo->listForTenant($tenantB))->toHaveCount(1)
      ->and($repo->findForTenant($tenantA, $idpA->id))->not
      ->toBeNull()
      ->and($repo->findForTenant($tenantA, $idpB->id))->toBeNull();

    $updated = $repo->updateForTenant($tenantA, $idpA->id, [
      'tenant_id' => $tenantB->id,
      'name' => 'Okta Updated',
    ]);

    expect($updated->name)
      ->toBe('Okta Updated')
      ->and($updated->tenant_id)->toBe($tenantA->id);

    $repo->deleteForTenant($tenantB, $idpB->id);
    expect($repo->findForTenant($tenantB, $idpB->id))->toBeNull();
});

it('throws when updating or deleting an identity provider outside tenant scope', function () {
    $repo = new EloquentIdentityProviderRepository();

    $tenantA = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);
    $tenantB = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'B']);

    $idpA = $repo->create($tenantA, [
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $fnUpdate = static fn () => $repo->updateForTenant($tenantB, $idpA->id, ['name' => 'Nope']);
    $fnDelete = fn () => $repo->deleteForTenant($tenantB, $idpA->id);

    expect($fnUpdate)
      ->toThrow(TenantScopedRecordNotFound::class)
      ->and($fnDelete)->toThrow(TenantScopedRecordNotFound::class);
});
