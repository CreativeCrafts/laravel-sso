<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\BeginLogin;
use CreativeCrafts\LaravelSso\Events\AuthAttemptCreated;
use CreativeCrafts\LaravelSso\Events\BeginLoginRedirectGenerated;
use CreativeCrafts\LaravelSso\Events\BeginLoginRequested;
use CreativeCrafts\LaravelSso\Exceptions\UnsupportedSsoProtocol;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcDriver;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(TestCase::class);

it('begins login via driver and dispatches events', function () {
    Event::fake([
      BeginLoginRequested::class,
      AuthAttemptCreated::class,
      BeginLoginRedirectGenerated::class,
    ]);

    config()->set('sso.drivers', [
      'oidc' => FakeOidcDriver::class,
    ]);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Okta',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $request = Request::create('/sso/start', 'GET', ['redirect_to' => '/after']);

    $useCase = app(BeginLogin::class);

    $result = $useCase->handle($request, $tenant, $connection->id);

    expect($result->redirectUrl)->toContain('state=');

    Event::assertDispatched(BeginLoginRequested::class);
    Event::assertDispatched(AuthAttemptCreated::class);
    Event::assertDispatched(BeginLoginRedirectGenerated::class);
});

it('throws when protocol has no configured driver', function () {
    config()->set('sso.drivers', []);

    $tenant = Tenant::query()->create(['ulid' => (string)Str::ulid(), 'name' => 'T1']);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'Azure',
      'protocol' => 'oidc',
      'enabled' => true,
      'config' => [],
    ]);

    $connection = Connection::query()->create([
      'tenant_id' => $tenant->id,
      'identity_provider_id' => $idp->id,
      'name' => 'Default',
      'enabled' => true,
      'guard' => 'web',
      'settings' => [],
    ]);

    $request = Request::create('/sso/start', 'GET');

    $useCase = app(BeginLogin::class);

    $fn = fn () => $useCase->handle($request, $tenant, $connection->id);

    expect($fn)->toThrow(UnsupportedSsoProtocol::class);
});
