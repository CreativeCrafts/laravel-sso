<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('widens external identity claims column and accepts encrypted payloads', function (): void {
    recreateOldExternalIdentitySchema();

    externalIdentityClaimsColumnUpgradeMigration()->up();

    $identity = ExternalIdentity::query()->create([
        'tenant_id' => 1,
        'identity_provider_id' => 1,
        'provider_subject' => 'sub-123',
        'email' => 'user@example.test',
        'display_name' => 'User One',
        'authenticatable_type' => 'App\\Models\\User',
        'authenticatable_id' => '1',
        'claims' => ['email' => 'user@example.test', 'groups' => ['staff']],
    ]);

    $stored = DB::table('sso_external_identities')->where('id', $identity->id)->value('claims');

    expect($stored)->toBeString()
        ->and($stored)->not->toContain('user@example.test')
        ->and($identity->fresh()?->claims)->toBe(['email' => 'user@example.test', 'groups' => ['staff']]);
});

it('is safe to run against an already current external identity schema', function (): void {
    expect(Schema::hasColumn('sso_external_identities', 'claims'))->toBeTrue();

    externalIdentityClaimsColumnUpgradeMigration()->up();

    expect(Schema::hasColumn('sso_external_identities', 'claims'))->toBeTrue();
});

function externalIdentityClaimsColumnUpgradeMigration(): Migration
{
    $migration = require __DIR__ . '/../../database/migrations/widen_sso_external_identity_claims_column.php.stub';

    expect($migration)->toBeInstanceOf(Migration::class);

    return $migration;
}

function recreateOldExternalIdentitySchema(): void
{
    Schema::disableForeignKeyConstraints();

    Schema::dropIfExists('sso_audit_logs');
    Schema::dropIfExists('sso_external_identities');
    Schema::dropIfExists('sso_auth_attempts');
    Schema::dropIfExists('sso_connections');
    Schema::dropIfExists('sso_identity_providers');
    Schema::dropIfExists('sso_tenants');

    Schema::enableForeignKeyConstraints();

    Schema::create('sso_tenants', function (Blueprint $table): void {
        $table->id();
        $table->ulid('ulid')->unique();
        $table->string('name')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('sso_identity_providers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained('sso_tenants')->cascadeOnDelete();
        $table->string('name');
        $table->string('protocol');
        $table->boolean('enabled')->default(true);
        $table->text('config')->nullable();
        $table->timestamps();
    });

    Schema::create('sso_external_identities', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained('sso_tenants')->cascadeOnDelete();
        $table->foreignId('identity_provider_id')->constrained('sso_identity_providers')->cascadeOnDelete();
        $table->string('provider_subject');
        $table->string('email')->nullable();
        $table->string('display_name')->nullable();
        $table->string('authenticatable_type');
        $table->string('authenticatable_id');
        $table->json('claims')->nullable();
        $table->timestamps();
    });

    DB::table('sso_tenants')->insert([
        'id' => 1,
        'ulid' => '01HR0000000000000000000001',
        'name' => 'Tenant One',
        'metadata' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sso_identity_providers')->insert([
        'id' => 1,
        'tenant_id' => 1,
        'name' => 'Example OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Config::set('sso.claims.encrypt_persisted', true);
}
