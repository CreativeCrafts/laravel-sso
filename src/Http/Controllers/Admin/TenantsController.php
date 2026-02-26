<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Admin;

use CreativeCrafts\LaravelSso\Http\Requests\Admin\TenantStoreRequest;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\TenantUpdateRequest;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TenantsController
{
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()->orderBy('id')->get();

        return response()->json([
          'data' => $tenants->map(fn (Tenant $t): array => $this->toArray($t))->all(),
        ]);
    }

    public function store(TenantStoreRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $tenant = Tenant::query()->create($payload);

        return response()->json([
          'data' => $this->toArray($tenant),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $tenant): JsonResponse
    {
        $model = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function update(TenantUpdateRequest $request, string $tenant): JsonResponse
    {
        $model = Tenant::query()->where('ulid', $tenant)->firstOrFail();

        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        $model->fill($payload);
        $model->save();

        return response()->json([
          'data' => $this->toArray($model),
        ]);
    }

    public function destroy(Request $request, string $tenant): JsonResponse
    {
        $model = Tenant::query()->where('ulid', $tenant)->firstOrFail();
        $model->delete();

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
          'id' => (int)$tenant->id,
          'ulid' => (string)$tenant->ulid,
          'name' => $tenant->name !== null ? (string)$tenant->name : null,
          'metadata' => $metadata,
        ];
    }
}
