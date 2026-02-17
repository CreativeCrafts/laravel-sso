<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentConnectionRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentIdentityProviderRepository;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('scopes connection CRUD by tenant', function () {
    $idpRepo = new EloquentIdentityProviderRepository();
    $repo = new EloquentConnectionRepository();

    $tenantA = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);
    $tenantB = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'B']);

    $idpA = $idpRepo->create($tenantA, [
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $idpB = $idpRepo->create($tenantB, [
      'name' => 'Azure',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [],
    ]);

    $connA = $repo->create($tenantA, [
      'tenant_id' => 999,
      'identity_provider_id' => $idpA->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => ['claims' => ['email' => 'email']],
    ]);

    $connB = $repo->create($tenantB, [
      'identity_provider_id' => $idpB->id,
      'name' => 'B-Conn',
      'enabled' => true,
      'guard' => 'admin',
      'settings' => [],
    ]);

    expect($connA->tenant_id)
      ->toBe($tenantA->id)
      ->and($connB->tenant_id)->toBe($tenantB->id)
      ->and($repo->listForTenant($tenantA))->toHaveCount(1)
      ->and($repo->listForTenant($tenantB))->toHaveCount(1)
      ->and($repo->findForTenant($tenantA, $connA->id))->not
      ->toBeNull()
      ->and($repo->findForTenant($tenantA, $connB->id))->toBeNull();

    $updated = $repo->updateForTenant($tenantA, $connA->id, [
      'tenant_id' => $tenantB->id,
      'name' => 'Default Updated',
      'guard' => 'web',
    ]);

    expect($updated->name)
      ->toBe('Default Updated')
      ->and($updated->tenant_id)->toBe($tenantA->id);

    $repo->deleteForTenant($tenantB, $connB->id);
    expect($repo->findForTenant($tenantB, $connB->id))->toBeNull();
});

it('throws when updating or deleting a connection outside tenant scope', function () {
    $idpRepo = new EloquentIdentityProviderRepository();
    $repo = new EloquentConnectionRepository();

    $tenantA = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'A']);
    $tenantB = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'B']);

    $idpA = $idpRepo->create($tenantA, [
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connA = $repo->create($tenantA, [
      'identity_provider_id' => $idpA->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $fnUpdate = static fn () => $repo->updateForTenant($tenantB, $connA->id, ['name' => 'Nope']);
    $fnDelete = fn () => $repo->deleteForTenant($tenantB, $connA->id);

    expect($fnUpdate)
      ->toThrow(TenantScopedRecordNotFound::class)
      ->and($fnDelete)->toThrow(TenantScopedRecordNotFound::class);
});
