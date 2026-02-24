<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('oidc callback endpoint returns 204 when state is valid', function () {
    config()->set('sso.drivers.oidc', FakeOidcCallbackDriver::class);

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

    // Create an attempt by hitting redirect endpoint once; parse state from Location.
    $redirectUrl = route('sso.redirect', [
      'tenant' => $tenant->ulid,
      'idp' => (string)$connection->id,
    ]);

    $redirectResp = $this->get($redirectUrl);
    $redirectResp->assertStatus(302);

    $location = (string)$redirectResp->headers->get('Location');
    parse_str((string)parse_url($location, PHP_URL_QUERY), $query);

    $state = $query['state'] ?? null;
    expect($state)->toBeString()->not->toBeEmpty();

    $callbackUrl = route('sso.oidc.callback', [
      'tenant' => $tenant->ulid,
      'idp' => (string)$connection->id,
    ]);

    $resp = $this->get($callbackUrl . '?state=' . urlencode((string)$state));

    $resp->assertNoContent();
});
