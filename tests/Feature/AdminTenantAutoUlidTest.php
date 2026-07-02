<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Support\UiApiTestCase;

uses(UiApiTestCase::class);

it('auto generates tenant ulid when omitted from admin api payload', function (): void {
    $response = $this->postJson('/admin/sso/tenants', [
        'name' => 'Generated ULID Tenant',
        'metadata' => ['domain' => 'generated.example.test'],
    ]);

    $response->assertCreated();

    $ulid = (string) $response->json('data.ulid');

    expect(strlen($ulid))->toBe(26);

    $this->getJson('/admin/sso/tenants/' . $ulid)
        ->assertOk()
        ->assertJsonFragment(['name' => 'Generated ULID Tenant']);
});

it('normalizes caller supplied tenant ulids to uppercase on create', function (): void {
    $response = $this->postJson('/admin/sso/tenants', [
        'ulid' => '01arz3ndektsv4rrffq69g5fav',
        'name' => 'Normalized ULID Tenant',
    ]);

    $response->assertCreated()
        ->assertJsonFragment(['ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']);
});
