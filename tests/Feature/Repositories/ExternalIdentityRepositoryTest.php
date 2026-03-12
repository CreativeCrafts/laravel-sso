<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentExternalIdentityRepository;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }
});

it('finds and upserts external identities through the repository', function () {
    $repository = new EloquentExternalIdentityRepository();

    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant A',
    ]);

    $identityProvider = IdentityProvider::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Example OIDC',
        'protocol' => 'oidc',
        'enabled' => true,
        'config' => [],
    ]);

    $user = User::query()->create([
        'email' => 'user@example.test',
        'name' => 'User One',
    ]);

    $created = $repository->upsertForUser(
        tenant: $tenant,
        identityProvider: $identityProvider,
        subject: 'sub-123',
        email: 'user@example.test',
        displayName: 'User One',
        claims: ['email' => 'user@example.test'],
        user: $user,
    );

    $found = $repository->findForTenantProviderAndSubject($tenant, $identityProvider, 'sub-123');

    expect($created->tenant_id)->toBe($tenant->id)
        ->and($created->identity_provider_id)->toBe($identityProvider->id)
        ->and($found?->id)->toBe($created->id);

    $updated = $repository->upsertForUser(
        tenant: $tenant,
        identityProvider: $identityProvider,
        subject: 'sub-123',
        email: 'updated@example.test',
        displayName: 'Updated User',
        claims: ['email' => 'updated@example.test'],
        user: $user,
    );

    expect($updated->id)->toBe($created->id)
        ->and($updated->email)->toBe('updated@example.test')
        ->and($updated->display_name)->toBe('Updated User');
});
