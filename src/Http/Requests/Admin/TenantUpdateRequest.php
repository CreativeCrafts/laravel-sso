<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class TenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantUlid = $this->route('tenant');

        return [
          'ulid' => ['sometimes', 'string', 'max:64', 'unique:sso_tenants,ulid,' . (is_string($tenantUlid) ? $tenantUlid : 'NULL') . ',ulid'],
          'name' => ['sometimes', 'nullable', 'string', 'max:255'],
          'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
