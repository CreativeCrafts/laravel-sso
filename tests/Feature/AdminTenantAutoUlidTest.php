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
