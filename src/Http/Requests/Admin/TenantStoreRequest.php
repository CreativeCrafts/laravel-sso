<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class TenantStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = config('sso.ui.gate', 'manageSso');
        $ability = is_string($ability) && $ability !== '' ? $ability : 'manageSso';

        return Gate::has($ability) ? Gate::allows($ability) : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
          'ulid' => ['required', 'string', 'max:64', 'unique:sso_tenants,ulid'],
          'name' => ['nullable', 'string', 'max:255'],
          'metadata' => ['nullable', 'array'],
        ];
    }
}
