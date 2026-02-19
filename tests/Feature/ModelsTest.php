<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

uses(TestCase::class);

it('defines tenant relations', function () {
    $tenant = new Tenant();

    expect($tenant->identityProviders())
      ->toBeInstanceOf(HasMany::class)
      ->and($tenant->connections())->toBeInstanceOf(HasMany::class)
      ->and($tenant->authAttempts())->toBeInstanceOf(HasMany::class)
      ->and($tenant->externalIdentities())->toBeInstanceOf(HasMany::class)
      ->and($tenant->auditLogs())->toBeInstanceOf(HasMany::class);
});

it('casts json fields to arrays', function () {
    $tenant = Tenant::query()->create([
      'ulid' => (string)Str::ulid(),
      'metadata' => ['plan' => 'pro'],
    ]);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Example',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => ['issuer' => 'https://issuer.example'],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => ['claims' => ['email' => 'email']],
    ]);

    $attempt = AuthAttempt::query()->create([
      'tenant_id' => $tenant->id,
      'connection_id' => $connection->id,
      'identity_provider_id' => $idp->id,
      'protocol' => 'oidc',
      'state' => 'state-1',
      'nonce' => 'nonce-1',
      'expires_at' => now()->addMinutes(10),
      'context' => ['redirect_to' => '/'],
    ]);

    $external = ExternalIdentity::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'provider_subject' => 'sub-123',
      'email' => 'user@example.test',
      'authenticatable_type' => 'App\\Models\\User',
      'authenticatable_id' => '1',
      'claims' => ['groups' => ['admin']],
    ]);

    expect($tenant->metadata)
      ->toBeArray()
      ->and($idp->config)->toBeArray()
      ->and($connection->settings)->toBeArray()
      ->and($attempt->context)->toBeArray()
      ->and($external->claims)->toBeArray();
});
