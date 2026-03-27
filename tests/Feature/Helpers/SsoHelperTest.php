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
