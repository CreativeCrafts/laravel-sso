<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\IdentityProviderStoreRequest;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\IdentityProviderUpdateRequest;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class IdentityProvidersController
{
    public function __construct(
        private IdentityProviderRepository $identityProviders,
    ) {
    }

    public function index(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        $items = $this->identityProviders->listForTenant($tenantModel);

        return response()->json([
          'data' => $items->map(fn (IdentityProvider $idp): array => $this->toArray($idp))->all(),
        ]);
    }

    public function store(IdentityProviderStoreRequest $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $idp = $this->identityProviders->create($tenantModel, $payload);

        return response()->json([
          'data' => $this->toArray($idp),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $tenant, int $idp): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        $model = $this->identityProviders->findForTenant($tenantModel, $idp);

        if (!$model instanceof IdentityProvider) {
            abort(404);
        }

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function update(IdentityProviderUpdateRequest $request, string $tenant, int $idp): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        try {
            $model = $this->identityProviders->updateForTenant($tenantModel, $idp, $payload);
        } catch (TenantScopedRecordNotFound) {
            abort(404);
        }

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function destroy(Request $request, string $tenant, int $idp): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        try {
            $this->identityProviders->deleteForTenant($tenantModel, $idp);
        } catch (TenantScopedRecordNotFound) {
            abort(404);
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(IdentityProvider $idp): array
    {
        /** @var array<string, mixed> $config */
        $config = is_array($idp->config) ? $idp->config : [];

        // Redact secrets by default.
        if (array_key_exists('client_secret', $config)) {
            $config['client_secret'] = '[redacted]';
        }

        return [
          'id' => (int)$idp->id,
          'tenant_id' => (int)$idp->tenant_id,
          'name' => (string)$idp->name,
          'protocol' => (string)$idp->protocol,
          'enabled' => (bool)$idp->enabled,
          'config' => $config,
        ];
    }
}
