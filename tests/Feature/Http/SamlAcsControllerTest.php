<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

uses(TestCase::class);

it('saml acs controller delegates to the core pipeline and redirects safely', function () {
    $tenant = new Tenant([
      'id' => 1,
      'ulid' => 'tenant_01',
      'name' => 'Tenant 01',
    ]);

    app()->instance(
        TenantResolver::class,
        new class ($tenant) implements TenantResolver {
          public function __construct(
              private readonly Tenant $tenant,
          ) {
          }

          public function resolve(Request $request): ?Tenant
          {
              return $this->tenant;
          }
      },
    );

    app()->instance(
        HandleCallback::class,
        new class () implements HandleCallback {
          public function handle(Request $request, Tenant $tenant, int $connectionId): DriverCallbackResult
          {
              return new DriverCallbackResult(
                  authenticated: true,
                  canonicalClaims: new Claims(
                      subject: 'subject-123',
                      email: 'user@example.test',
                      displayName: 'User Example',
                      emailVerified: null,
                      groups: [],
                      normalized: ['protocol' => 'saml'],
                  ),
                  subject: 'subject-123',
                  email: 'user@example.test',
                  displayName: 'User Example',
                  claims: ['protocol' => 'saml'],
                  context: [],
                  error: null,
              );
          }
      },
    );

    $provisioned = false;

    app()->instance(
        ProvisionAndLink::class,
        new class ($provisioned) implements ProvisionAndLink {
          public function __construct(
              private bool &$provisioned,
          ) {
          }

          public function handle(Request $request, Tenant $tenant, int $connectionId, DriverCallbackResult $callback): Authenticatable
          {
              $this->provisioned = true;

              return new User([
                'name' => 'User Example',
                'email' => 'user@example.test',
              ]);
          }
      },
    );

    app()->instance(
        AuthAttemptRepository::class,
        new class () implements AuthAttemptRepository {
          public function findByState(Tenant $tenant, string $state): ?AuthAttempt
          {
              return new AuthAttempt([
                'tenant_id' => $tenant->id,
                'state' => $state,
                'redirect_to' => '/dashboard',
              ]);
          }
      },
    );

    $response = $this->post(route('sso.saml.acs', [
      'tenant' => $tenant->ulid,
      'idp' => '10',
    ]), [
      'SAMLResponse' => base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" />'),
      'RelayState' => 'state-123',
    ]);

    $response->assertRedirect('/dashboard');
    expect($provisioned)->toBeTrue();
});

it('returns 204 when the core callback result is not authenticated', function () {
    $tenant = new Tenant([
      'id' => 2,
      'ulid' => 'tenant_02',
      'name' => 'Tenant 02',
    ]);

    app()->instance(
        TenantResolver::class,
        new class ($tenant) implements TenantResolver {
          public function __construct(
              private readonly Tenant $tenant,
          ) {
          }

          public function resolve(Request $request): ?Tenant
          {
              return $this->tenant;
          }
      }
    );

    app()->instance(
        HandleCallback::class,
        new class () implements HandleCallback {
          public function handle(Request $request, Tenant $tenant, int $connectionId): DriverCallbackResult
          {
              return new DriverCallbackResult(
                  authenticated: false,
                  canonicalClaims: new Claims(
                      subject: 'subject-123',
                      email: 'user@example.test',
                      displayName: 'User Example',
                      emailVerified: null,
                      groups: [],
                      normalized: [],
                  ),
              );
          }
      },
    );

    app()->instance(
        ProvisionAndLink::class,
        new class () implements ProvisionAndLink {
          public function handle(Request $request, Tenant $tenant, int $connectionId, DriverCallbackResult $callback): Authenticatable
          {
              throw new RuntimeException('ProvisionAndLink should not be called when callback is unauthenticated.');
          }
      },
    );

    app()->instance(
        AuthAttemptRepository::class,
        new class () implements AuthAttemptRepository {
          public function findByState(Tenant $tenant, string $state): ?AuthAttempt
          {
              return null;
          }
      },
    );

    $response = $this->post(route('sso.saml.acs', [
      'tenant' => $tenant->ulid,
      'idp' => '11',
    ]), [
      'SAMLResponse' => base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" />'),
      'RelayState' => 'state-456',
    ]);

    $response->assertNoContent();
});
