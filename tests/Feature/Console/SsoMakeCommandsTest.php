<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);
uses(SsoTestHelpers::class);

it('creates tenant, idp, and connection via commands', function (): void {
    $this->artisan('sso:make-tenant Example')
        ->assertExitCode(0);

    $tenant = Tenant::query()->firstOrFail();

    $this->artisan('sso:make-idp ' . $tenant->ulid . ' MyIdp --protocol=oidc')
        ->assertExitCode(0);

    $idp = IdentityProvider::query()->where('tenant_id', $tenant->id)->firstOrFail();

    $this->artisan('sso:make-connection ' . $tenant->ulid . ' ' . $idp->id . ' MyConn --guard=web')
        ->assertExitCode(0);

    $connection = Connection::query()->where('tenant_id', $tenant->id)->first();
    expect($connection)->not->toBeNull();
});
