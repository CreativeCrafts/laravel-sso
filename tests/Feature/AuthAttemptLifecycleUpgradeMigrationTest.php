<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('adds auth attempt lifecycle columns and backfills old schemas', function (): void {
    recreateOldAuthAttemptSchema();

    DB::table('sso_tenants')->insert([
        'id' => 1,
        'ulid' => '01HR0000000000000000000001',
        'name' => 'Tenant One',
        'metadata' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sso_auth_attempts')->insert([
        [
            'id' => 1,
            'tenant_id' => 1,
            'identity_provider_id' => null,
            'connection_id' => null,
            'protocol' => 'oidc',
            'state' => 'pending-state',
            'nonce' => null,
            'code_verifier' => null,
            'redirect_to' => null,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'ip' => null,
            'user_agent' => null,
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 2,
            'tenant_id' => 1,
            'identity_provider_id' => null,
            'connection_id' => null,
            'protocol' => 'oidc',
            'state' => 'consumed-state',
            'nonce' => null,
            'code_verifier' => null,
            'redirect_to' => null,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
            'ip' => null,
            'user_agent' => null,
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    authAttemptLifecycleUpgradeMigration()->up();

    expect(Schema::hasColumn('sso_auth_attempts', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('sso_auth_attempts', 'validating_at'))->toBeTrue()
        ->and(Schema::hasColumn('sso_auth_attempts', 'failed_at'))->toBeTrue()
        ->and(DB::table('sso_auth_attempts')->where('state', 'pending-state')->value('status'))->toBe('pending')
        ->and(DB::table('sso_auth_attempts')->where('state', 'consumed-state')->value('status'))->toBe('consumed');
});

it('is safe to run against an already current auth attempt schema', function (): void {
    expect(Schema::hasColumn('sso_auth_attempts', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('sso_auth_attempts', 'validating_at'))->toBeTrue()
        ->and(Schema::hasColumn('sso_auth_attempts', 'failed_at'))->toBeTrue();

    authAttemptLifecycleUpgradeMigration()->up();

    expect(Schema::hasColumn('sso_auth_attempts', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('sso_auth_attempts', 'validating_at'))->toBeTrue()
        ->and(Schema::hasColumn('sso_auth_attempts', 'failed_at'))->toBeTrue();
});

function authAttemptLifecycleUpgradeMigration(): Migration
{
    $migration = require __DIR__ . '/../../database/migrations/add_lifecycle_fields_to_sso_auth_attempts.php.stub';

    expect($migration)->toBeInstanceOf(Migration::class);

    return $migration;
}

function recreateOldAuthAttemptSchema(): void
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

    Schema::create('sso_auth_attempts', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('identity_provider_id')->nullable();
        $table->unsignedBigInteger('connection_id')->nullable();
        $table->string('protocol');
        $table->string('state');
        $table->string('nonce')->nullable();
        $table->text('code_verifier')->nullable();
        $table->text('redirect_to')->nullable();
        $table->timestamp('expires_at');
        $table->timestamp('consumed_at')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->json('context')->nullable();
        $table->timestamps();

        $table->unique(['tenant_id', 'state']);
        $table->index(['tenant_id', 'expires_at']);
        $table->index(['tenant_id', 'consumed_at']);
    });
}
