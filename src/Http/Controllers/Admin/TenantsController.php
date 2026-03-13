<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\TenantStoreRequest;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\TenantUpdateRequest;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TenantsController
{
    public function __construct(
        private TenantRepository $tenants,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->tenants->listAll();

        return response()->json([
            'data' => $items->map(fn (Tenant $tenant): array => $this->toArray($tenant))->all(),
        ]);
    }

    public function store(TenantStoreRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $tenant = $this->tenants->create($payload);

        return response()->json([
            'data' => $this->toArray($tenant),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $tenant): JsonResponse
    {
        try {
            $model = $this->tenants->getByUlid($tenant);
        } catch (TenantNotFound) {
            abort(404);
        }

        return response()->json([
            'data' => $this->toArray($model),
        ]);
    }

    public function update(TenantUpdateRequest $request, string $tenant): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        try {
            $model = $this->tenants->updateByUlid($tenant, $payload);
        } catch (TenantNotFound) {
            abort(404);
        }

        return response()->json([
            'data' => $this->toArray($model),
        ]);
    }

    public function destroy(Request $request, string $tenant): JsonResponse
    {
        try {
            $this->tenants->deleteByUlid($tenant);
        } catch (TenantNotFound) {
            abort(404);
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Tenant $tenant): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $tenant->metadata;

        return [
            'id' => (int) $tenant->id,
            'ulid' => (string) $tenant->ulid,
            'name' => $tenant->name !== null ? (string) $tenant->name : null,
            'metadata' => $metadata,
        ];
    }
}
