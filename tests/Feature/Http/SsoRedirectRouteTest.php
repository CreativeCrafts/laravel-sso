<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcDriver;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('redirect endpoint returns 302 to driver start URL', function () {
    config()->set('sso.drivers.oidc', FakeOidcDriver::class);

    $tenant = Tenant::query()->create([
      'ulid' => (string)Str::ulid(),
      'name' => 'T1',
    ]);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'settings' => [],
    ]);

    $url = route('sso.redirect', [
      'tenant' => $tenant->ulid,
      'idp' => (string)$connection->id,
    ]);

    $resp = $this->get($url);

    $resp->assertStatus(302);
    expect($resp->headers->get('Location'))->toStartWith('https://idp.example/authorize?state=');
});
