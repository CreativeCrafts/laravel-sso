<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Http\Requests\Admin\Rules\UniqueTenantUlid;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Support\Facades\Validator;

it('validates tenant ulid uniqueness case insensitively', function (): void {
    Tenant::query()->create([
        'ulid' => '01arz3ndektsv4rrffq69g5fav',
        'name' => 'Existing',
    ]);

    $validator = Validator::make(
        ['ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
        ['ulid' => [new UniqueTenantUlid()]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('ulid'))->toBeTrue();
});

it('ignores the current tenant id when validating ulid updates', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => '01arz3ndektsv4rrffq69g5fav',
        'name' => 'Existing',
    ]);

    $validator = Validator::make(
        ['ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
        ['ulid' => [new UniqueTenantUlid($tenant->id)]],
    );

    expect($validator->fails())->toBeFalse();
});

it('validates non ulid tenant keys with exact matching only', function (): void {
    Tenant::query()->create([
        'ulid' => 'tenant_ca',
        'name' => 'Legacy Tenant',
    ]);

    $validator = Validator::make(
        ['ulid' => 'tenant_ca'],
        ['ulid' => [new UniqueTenantUlid()]],
    );

    expect($validator->fails())->toBeTrue();

    $allowed = Validator::make(
        ['ulid' => 'tenant_cb'],
        ['ulid' => [new UniqueTenantUlid()]],
    );

    expect($allowed->fails())->toBeFalse();
});
