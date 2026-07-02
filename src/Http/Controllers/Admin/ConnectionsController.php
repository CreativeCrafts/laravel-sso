<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Core\ConnectionRouteResolver;
use CreativeCrafts\LaravelSso\Core\IdentityProviderRouteResolver;
use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\ConnectionStoreRequest;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\ConnectionUpdateRequest;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ConnectionsController
{
    public function __construct(
        private TenantRepository $tenants,
        private ConnectionRepository $connections,
        private IdentityProviderRepository $identityProviders,
        private ConnectionRouteResolver $connectionRoutes,
        private IdentityProviderRouteResolver $identityProviderRoutes,
    ) {
    }

    public function index(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        $items = $this->connections->listForTenant($tenantModel);

        return response()->json([
          'data' => $items->map(fn (Connection $c): array => $this->toArray($c))->all(),
        ]);
    }

    public function store(ConnectionStoreRequest $request, string $tenant): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $identityProviderId = $this->resolveIdentityProviderReference($tenantModel, $payload['identity_provider_id'] ?? null);

        if ($identityProviderId === null) {
            return response()->json([
              'message' => 'Identity provider not found within tenant scope.',
              'errors' => ['identity_provider_id' => ['Invalid identity provider for this tenant.']],
            ], 422);
        }

        $payload['identity_provider_id'] = $identityProviderId;

        $connection = $this->connections->create($tenantModel, $payload);

        return response()->json([
          'data' => $this->toArray($connection),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $tenant, string $connection): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        $model = $this->connections->findForTenantByRouteKey($tenantModel, $connection);

        if (!$model instanceof Connection) {
            abort(404);
        }

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function update(ConnectionUpdateRequest $request, string $tenant, string $connection): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        if (array_key_exists('identity_provider_id', $payload)) {
            $identityProviderId = $this->resolveIdentityProviderReference($tenantModel, $payload['identity_provider_id'] ?? null);

            if ($identityProviderId === null) {
                return response()->json([
                  'message' => 'Identity provider not found within tenant scope.',
                  'errors' => ['identity_provider_id' => ['Invalid identity provider for this tenant.']],
                ], 422);
            }

            $payload['identity_provider_id'] = $identityProviderId;
        }

        try {
            $model = $this->connections->updateForTenant(
                $tenantModel,
                $this->connectionRoutes->resolveId($tenantModel, $connection),
                $payload,
            );
        } catch (TenantScopedRecordNotFound) {
            abort(404);
        }

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function destroy(Request $request, string $tenant, string $connection): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        try {
            $this->connections->deleteForTenant(
                $tenantModel,
                $this->connectionRoutes->resolveId($tenantModel, $connection),
            );
        } catch (TenantScopedRecordNotFound) {
            abort(404);
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Connection $connection): array
    {
        /** @var array<string, mixed> $settings */
        $settings = $connection->settings;

        return [
          'id' => (int)$connection->id,
          'ulid' => (string) $connection->ulid,
          'tenant_id' => (int)$connection->tenant_id,
          'identity_provider_id' => (int)$connection->identity_provider_id,
          'name' => (string)$connection->name,
          'enabled' => (bool)$connection->enabled,
          'guard' => $connection->guard !== null ? (string)$connection->guard : null,
          'settings' => $settings,
        ];
    }

    private function resolveTenant(string $tenant): Tenant
    {
        try {
            return $this->tenants->getByUlid($tenant);
        } catch (TenantNotFound) {
            abort(404);
        }
    }

    private function resolveIdentityProviderReference(Tenant $tenant, mixed $reference): ?int
    {
        if (is_int($reference)) {
            $idp = $this->identityProviders->findForTenant($tenant, $reference);

            return $idp instanceof IdentityProvider ? (int) $idp->id : null;
        }

        if (is_string($reference) && $reference !== '') {
            if (TenantRouteKey::isNumericId($reference)) {
                $idp = $this->identityProviders->findForTenant($tenant, (int) $reference);

                return $idp instanceof IdentityProvider ? (int) $idp->id : null;
            }

            try {
                return $this->identityProviderRoutes->resolveId($tenant, $reference);
            } catch (TenantScopedRecordNotFound) {
                return null;
            }
        }

        return null;
    }
}
