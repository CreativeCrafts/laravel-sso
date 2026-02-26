<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
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
        private ConnectionRepository $connections,
        private IdentityProviderRepository $identityProviders,
    ) {
    }

    public function index(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        $items = $this->connections->listForTenant($tenantModel);

        return response()->json([
          'data' => $items->map(fn (Connection $c): array => $this->toArray($c))->all(),
        ]);
    }

    public function store(ConnectionStoreRequest $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        // The FormRequest rules guarantee this is an integer, but phpstan sees `mixed`.
        $idpIdRaw = $payload['identity_provider_id'] ?? null;
        if (!is_int($idpIdRaw)) {
            return response()->json([
              'message' => 'Invalid identity provider id.',
              'errors' => ['identity_provider_id' => ['The identity_provider_id field must be an integer.']],
            ], 422);
        }

        $idp = $this->identityProviders->findForTenant($tenantModel, $idpIdRaw);
        if (!$idp instanceof IdentityProvider) {
            return response()->json([
              'message' => 'Identity provider not found within tenant scope.',
              'errors' => ['identity_provider_id' => ['Invalid identity provider for this tenant.']],
            ], 422);
        }

        $connection = $this->connections->create($tenantModel, $payload);

        return response()->json([
          'data' => $this->toArray($connection),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $tenant, int $connection): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        $model = $this->connections->findForTenant($tenantModel, $connection);

        if (!$model instanceof Connection) {
            abort(404);
        }

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function update(ConnectionUpdateRequest $request, string $tenant, int $connection): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        if (array_key_exists('identity_provider_id', $payload)) {
            $idpIdRaw = $payload['identity_provider_id'] ?? null;

            if (!is_int($idpIdRaw)) {
                return response()->json([
                  'message' => 'Invalid identity provider id.',
                  'errors' => ['identity_provider_id' => ['The identity_provider_id field must be an integer.']],
                ], 422);
            }

            $idp = $this->identityProviders->findForTenant($tenantModel, $idpIdRaw);
            if (!$idp instanceof IdentityProvider) {
                return response()->json([
                  'message' => 'Identity provider not found within tenant scope.',
                  'errors' => ['identity_provider_id' => ['Invalid identity provider for this tenant.']],
                ], 422);
            }
        }

        try {
            $model = $this->connections->updateForTenant($tenantModel, $connection, $payload);
        } catch (TenantScopedRecordNotFound) {
            abort(404);
        }

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function destroy(Request $request, string $tenant, int $connection): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        try {
            $this->connections->deleteForTenant($tenantModel, $connection);
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
          'tenant_id' => (int)$connection->tenant_id,
          'identity_provider_id' => (int)$connection->identity_provider_id,
          'name' => (string)$connection->name,
          'enabled' => (bool)$connection->enabled,
          'guard' => $connection->guard !== null ? (string)$connection->guard : null,
          'settings' => $settings,
        ];
    }
}
