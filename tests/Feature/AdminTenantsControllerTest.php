<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Support\UiApiTestCase;
use Illuminate\Support\Str;

uses(UiApiTestCase::class);

it('lists tenants through the admin tenants index endpoint', function () {
    $tenantA = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant A',
        'metadata' => ['domain' => 'a.example.test'],
    ]);

    $tenantB = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant B',
        'metadata' => ['domain' => 'b.example.test'],
    ]);

    $response = $this->getJson('/admin/sso/tenants');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment([
        'ulid' => $tenantA->ulid,
        'name' => 'Tenant A',
    ]);
    $response->assertJsonFragment([
        'ulid' => $tenantB->ulid,
        'name' => 'Tenant B',
    ]);
});

it('creates updates shows and deletes tenants through the admin tenants endpoints', function () {
    $ulid = (string) Str::ulid();

    $store = $this->postJson('/admin/sso/tenants', [
        'ulid' => $ulid,
        'name' => 'Tenant Created',
        'metadata' => ['domain' => 'created.example.test'],
    ]);

    $store->assertCreated();
    $store->assertJsonFragment([
        'ulid' => $ulid,
        'name' => 'Tenant Created',
    ]);

    $show = $this->getJson('/admin/sso/tenants/' . $ulid);

    $show->assertOk();
    $show->assertJsonFragment([
        'ulid' => $ulid,
        'name' => 'Tenant Created',
    ]);

    $update = $this->putJson('/admin/sso/tenants/' . $ulid, [
        'name' => 'Tenant Updated',
        'metadata' => ['domain' => 'updated.example.test'],
    ]);

    $update->assertOk();
    $update->assertJsonFragment([
        'ulid' => $ulid,
        'name' => 'Tenant Updated',
    ]);

    $delete = $this->deleteJson('/admin/sso/tenants/' . $ulid);

    $delete->assertNoContent();

    $this->getJson('/admin/sso/tenants/' . $ulid)->assertNotFound();
});
