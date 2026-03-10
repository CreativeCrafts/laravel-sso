<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fakes\Drivers\FakeOidcCallbackDriver;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

it('completes the OIDC login flow, provisions a user and redirects to the intended location', function () {
    // Use the fake OIDC callback driver which returns a known canonical claim set.
    config()->set('sso.drivers.oidc', FakeOidcCallbackDriver::class);

    // Ensure the users table exists and configure the auth provider. This allows provisioning and login.
    if (!Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            // Casting type hints on the table parameter can cause a mismatch in Pest tests.
            // Omitting the type hint avoids runtime type errors while still allowing column definitions.
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);

    // Create a tenant, identity provider, and connection.
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
      'guard' => 'web',
      'settings' => [],
    ]);

    // Define an intended redirect target.
    $intended = '/dashboard';

    // Begin the login flow by hitting the redirect endpoint with a redirect_to parameter.
    $redirectUrl = route('sso.redirect', [
      'tenant' => $tenant->ulid,
      'idp' => (string)$connection->id,
    ]);

    $response = $this->get($redirectUrl . '?redirect_to=' . urlencode($intended));

    // Capture the state from the Location header.
    $response->assertStatus(302);
    $location = (string)$response->headers->get('Location');
    parse_str((string)parse_url($location, PHP_URL_QUERY), $query);
    $state = $query['state'] ?? null;
    expect($state)->toBeString()->not->toBeEmpty();

    // Hit the callback endpoint with the captured state.
    $callbackUrl = route('sso.oidc.callback', [
      'tenant' => $tenant->ulid,
      'idp' => (string)$connection->id,
    ]);
    $callbackResp = $this->get($callbackUrl . '?state=' . urlencode((string)$state));

    // The callback should redirect the authenticated user to the intended location.
    $callbackResp->assertRedirect($intended);

    // After the callback, the user should be authenticated.
    expect(auth('web')->check())->toBeTrue();

    // The external identity should be linked to the provisioned user.
    $external = ExternalIdentity::query()
      ->where('tenant_id', $tenant->id)
      ->where('identity_provider_id', $idp->id)
      ->first();
    expect($external)->not->toBeNull();
});
