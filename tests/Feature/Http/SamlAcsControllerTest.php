<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
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

function mockConnectionRepositoryForAcs(Tenant $tenant, string $routeKey, int $connectionId): void
{
    app()->instance(
        ConnectionRepository::class,
        new class ($tenant, $routeKey, $connectionId) implements ConnectionRepository {
            public function __construct(
                private readonly Tenant $tenant,
                private readonly string $routeKey,
                private readonly int $connectionId,
            ) {
            }

            public function create(Tenant $tenant, array $attributes): Connection
            {
                throw new RuntimeException('Not implemented.');
            }

            public function listForTenant(Tenant $tenant): \Illuminate\Support\Collection
            {
                throw new RuntimeException('Not implemented.');
            }

            public function findForTenant(Tenant $tenant, int $id): ?Connection
            {
                throw new RuntimeException('Not implemented.');
            }

            public function findForTenantByRouteKey(Tenant $tenant, string $routeKey): ?Connection
            {
                if ($tenant->id !== $this->tenant->id || $routeKey !== $this->routeKey) {
                    return null;
                }

                return new Connection([
                    'id' => $this->connectionId,
                    'tenant_id' => $this->tenant->id,
                    'ulid' => $this->routeKey,
                    'enabled' => true,
                ]);
            }

            public function updateForTenant(Tenant $tenant, int $id, array $attributes): Connection
            {
                throw new RuntimeException('Not implemented.');
            }

            public function deleteForTenant(Tenant $tenant, int $id): void
            {
                throw new RuntimeException('Not implemented.');
            }
        },
    );
}

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
                        emailVerified: true,
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

    app()->instance(
        AuthAttemptService::class,
        new class () implements AuthAttemptService {
            public function create(
                Tenant $tenant,
                string $protocol,
                ?Connection $connection = null,
                ?IdentityProvider $identityProvider = null,
                ?string $redirectTo = null,
                ?string $codeVerifier = null,
                bool $withNonce = true,
                array $context = [],
                ?string $ip = null,
                ?string $userAgent = null,
            ): AuthAttempt {
                throw new RuntimeException('Not implemented.');
            }

            public function reserveForValidation(
                Tenant $tenant,
                string $state,
                ?int $expectedConnectionId = null,
                ?int $expectedIdentityProviderId = null,
            ): AuthAttempt {
                throw new RuntimeException('Not implemented.');
            }

            public function markConsumed(AuthAttempt $attempt): AuthAttempt
            {
                return $attempt;
            }

            public function markValidationFailed(AuthAttempt $attempt): AuthAttempt
            {
                return $attempt;
            }

            public function consumeByState(
                Tenant $tenant,
                string $state,
                ?int $expectedConnectionId = null,
                ?int $expectedIdentityProviderId = null,
            ): AuthAttempt {
                throw new RuntimeException('Not implemented.');
            }
        },
    );

    mockConnectionRepositoryForAcs($tenant, '10', 10);

    $response = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => '10',
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
        },
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
                        emailVerified: true,
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

    mockConnectionRepositoryForAcs($tenant, '11', 11);

    $response = $this->post(route('sso.saml.acs', [
        'tenant' => $tenant->ulid,
        'connection' => '11',
    ]), [
        'SAMLResponse' => base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" />'),
        'RelayState' => 'state-456',
    ]);

    $response->assertNoContent();
});
