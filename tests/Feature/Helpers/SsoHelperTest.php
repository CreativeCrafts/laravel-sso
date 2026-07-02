<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);
uses(SsoTestHelpers::class);

it('builds redirect url', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    $url = sso_redirect_url($tenant->ulid, $connection->id);

    expect($url)->toContain('/sso/');
});

it('appends redirect_to query parameter when provided', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    $url = sso_redirect_url($tenant->ulid, $connection->id, '/dashboard');

    expect($url)
        ->toContain('/sso/')
        ->toContain('redirect_to=' . rawurlencode('/dashboard'));
});
