<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('creates all required sso tables', function () {
    $tables = [
      'sso_tenants',
      'sso_identity_providers',
      'sso_connections',
      'sso_auth_attempts',
      'sso_external_identities',
      'sso_audit_logs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))
          ->toBeTrue();
    }
});

it('stores encrypted identity provider config in a text-compatible column', function (): void {
    $column = collect(Schema::getColumns('sso_identity_providers'))
        ->firstWhere('name', 'config');

    expect($column)->toBeArray();

    /** @var array{name: string, type_name?: string, type?: string} $column */
    $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));

    expect($type)->not->toContain('json');
});

it('persists encrypted identity provider config as ciphertext while exposing arrays through the model', function (): void {
    $tenantId = DB::table('sso_tenants')->insertGetId([
        'ulid' => '01J00000000000000000090001',
        'name' => 'Encrypted Config Tenant',
        'metadata' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $idp = IdentityProvider::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Encrypted IdP',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [
            'issuer' => 'https://issuer.example.test',
            'client_id' => 'client-one',
        ],
    ]);

    $rawConfig = DB::table('sso_identity_providers')
        ->where('id', $idp->id)
        ->value('config');

    expect($rawConfig)->toBeString()
        ->and($rawConfig)->not->toContain('issuer.example.test')
        ->and(json_decode((string) $rawConfig, true))->toBeNull()
        ->and($idp->refresh()->config)->toBe([
            'issuer' => 'https://issuer.example.test',
            'client_id' => 'client-one',
        ]);
});
