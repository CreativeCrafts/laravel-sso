<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\BeginLogin;
use CreativeCrafts\LaravelSso\Drivers\OidcDriver;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(TestCase::class);

it('builds an OIDC authorization redirect URL with state, nonce, and PKCE S256', function () {
    config()->set('sso.drivers.oidc', OidcDriver::class);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'OIDC',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [
        'discovery_enabled' => false,
        'endpoints' => [
          'authorization' => 'https://idp.example/authorize',
          'token' => 'https://idp.example/token',
          'jwks' => 'https://idp.example/jwks',
        ],
        'client_id' => 'client-123',
        'redirect_uri' => 'https://app.example/sso/callback',
        'scope' => 'openid email profile',
        'response_type' => 'code',
      ],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $request = Request::create('/sso/begin', 'GET');

    $useCase = app(BeginLogin::class);
    $result = $useCase->handle($request, $tenant, $connection->id);

    expect($result->redirectUrl)->toStartWith('https://idp.example/authorize?');

    parse_str(parse_url($result->redirectUrl, PHP_URL_QUERY) ?: '', $query);

    expect($query)
      ->toHaveKeys([
        'client_id',
        'redirect_uri',
        'response_type',
        'scope',
        'state',
        'nonce',
        'code_challenge',
        'code_challenge_method',
      ])
      ->and($query['code_challenge_method'])->toBe('S256');

    $attempt = AuthAttempt::query()
      ->where('tenant_id', $tenant->id)
      ->where('connection_id', $connection->id)
      ->latest('id')
      ->first();

    expect($attempt)->not
      ->toBeNull()
      ->and($attempt?->nonce)->not
      ->toBeNull()
      ->and($attempt?->code_verifier)->not->toBeNull();
});
