<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\DefaultHostnameResolver;
use CreativeCrafts\LaravelSso\Core\TenantRouteKey;

it('resolves literal ip addresses', function (): void {
    $resolver = new DefaultHostnameResolver();

    expect($resolver->resolve('93.184.216.34'))->toBe(['93.184.216.34']);
});

it('returns empty list for blank hostnames', function (): void {
    $resolver = new DefaultHostnameResolver();

    expect($resolver->resolve(''))->toBe([]);
});

it('resolves dns hostnames to at least one ip address', function (): void {
    $resolver = new DefaultHostnameResolver();

    $addresses = $resolver->resolve('example.com');

    expect($addresses)->not->toBeEmpty()
        ->and($addresses)->each->toMatch('/^(\d{1,3}\.){3}\d{1,3}$|^[0-9a-f:]+$/i');
});

it('identifies ulid and numeric route keys', function (): void {
    expect(TenantRouteKey::looksLikeUlid('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeTrue()
        ->and(TenantRouteKey::looksLikeUlid('not-a-ulid'))->toBeFalse()
        ->and(TenantRouteKey::isNumericId('42'))->toBeTrue()
        ->and(TenantRouteKey::isNumericId('abc'))->toBeFalse();
});
