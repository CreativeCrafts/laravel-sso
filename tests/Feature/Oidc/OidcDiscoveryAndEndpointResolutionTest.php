<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(TestCase::class);

it('resolves endpoints from manual config without discovery', function () {
    Http::fake();

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Manual OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'discovery_enabled' => true,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
          'userinfo' => 'https://idp.example/userinfo',
        ],
      ],
    ]);

    $resolver = app(OidcEndpointResolver::class);
    $endpoints = $resolver->resolve($idp);

    expect($endpoints->fromDiscovery)
      ->toBeFalse()
      ->and($endpoints->authorizationEndpoint)->toBe('https://idp.example/authorize');

    Http::assertNothingSent();
});

it('resolves endpoints via discovery and caches results', function () {
    config()?->set('sso.oidc.discovery.cache_ttl_seconds', 3600);

    Http::fake([
      'https://issuer.example/.well-known/openid-configuration' => Http::response([
        'issuer' => 'https://issuer.example',
        'authorization_endpoint' => 'https://issuer.example/authorize',
        'token_endpoint' => 'https://issuer.example/token',
        'jwks_uri' => 'https://issuer.example/jwks',
        'userinfo_endpoint' => 'https://issuer.example/userinfo',
      ], 200),
    ]);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Discovery OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'issuer' => 'https://issuer.example',
        'discovery_enabled' => true,
      ],
    ]);

    $resolver = app(OidcEndpointResolver::class);

    $first = $resolver->resolve($idp);
    $second = $resolver->resolve($idp);

    expect($first->fromDiscovery)
      ->toBeTrue()
      ->and($first->authorizationEndpoint)->toBe('https://issuer.example/authorize')
      ->and($second->authorizationEndpoint)->toBe('https://issuer.example/authorize');

    Http::assertSentCount(1);
});
