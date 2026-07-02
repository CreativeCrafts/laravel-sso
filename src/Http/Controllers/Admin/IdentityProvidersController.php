<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Core\IdentityProviderRouteResolver;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
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
        private TenantRepository $tenants,
        private IdentityProviderRepository $identityProviders,
        private IdentityProviderRouteResolver $identityProviderRoutes,
    ) {
    }

    public function index(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        $items = $this->identityProviders->listForTenant($tenantModel);

        return response()->json([
            'data' => $items->map(fn (IdentityProvider $idp): array => $this->toArray($idp))->all(),
        ]);
    }

    public function store(IdentityProviderStoreRequest $request, string $tenant): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $idp = $this->identityProviders->create($tenantModel, $payload);

        return response()->json([
            'data' => $this->toArray($idp),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $tenant, string $idp): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);
        $model = $this->identityProviders->findForTenantByRouteKey($tenantModel, $idp);

        if (!$model instanceof IdentityProvider) {
            abort(404);
        }

        return response()->json([
            'data' => $this->toArray($model),
        ]);
    }

    public function update(IdentityProviderUpdateRequest $request, string $tenant, string $idp): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        try {
            $model = $this->identityProviders->updateForTenant(
                $tenantModel,
                $this->identityProviderRoutes->resolveId($tenantModel, $idp),
                $payload,
            );
        } catch (TenantScopedRecordNotFound) {
            abort(404);
        }

        return response()->json([
            'data' => $this->toArray($model),
        ]);
    }

    public function destroy(Request $request, string $tenant, string $idp): JsonResponse
    {
        $tenantModel = $this->resolveTenant($tenant);

        try {
            $this->identityProviders->deleteForTenant(
                $tenantModel,
                $this->identityProviderRoutes->resolveId($tenantModel, $idp),
            );
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

        $config = $this->redactConfig($config);

        return [
            'id' => (int) $idp->id,
            'ulid' => (string) $idp->ulid,
            'tenant_id' => (int) $idp->tenant_id,
            'name' => (string) $idp->name,
            'protocol' => (string) $idp->protocol,
            'enabled' => (bool) $idp->enabled,
            'config' => $config,
        ];
    }

    /**
     * @param array<mixed, mixed> $config
     * @return array<mixed, mixed>
     */
    private function redactConfig(array $config): array
    {
        $sanitized = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->redactConfig($value);
                continue;
            }

            if (!is_string($key)) {
                $sanitized[$key] = $value;
                continue;
            }

            $sanitized[$key] = $this->isSensitiveKey($key) ? '[redacted]' : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        $exact = [
            'client_secret',
            'secret',
            'shared_secret',
            'private_key',
            'signing_key',
            'encryption_key',
            'certificate',
            'cert',
            'api_key',
            'app_key',
            'app_secret',
            'token',
            'bearer_token',
            'access_token',
            'refresh_token',
            'password',
        ];

        if (in_array($normalized, $exact, true)) {
            return true;
        }

        return str_ends_with($normalized, '_secret')
            || str_ends_with($normalized, '_token')
            || str_ends_with($normalized, '_key');
    }

    private function resolveTenant(string $tenant): Tenant
    {
        try {
            return $this->tenants->getByUlid($tenant);
        } catch (TenantNotFound) {
            abort(404);
        }
    }
}
