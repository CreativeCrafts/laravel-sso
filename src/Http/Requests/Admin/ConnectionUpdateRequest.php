<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConnectionUpdateRequest extends FormRequest
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
          'identity_provider_id' => ['sometimes', 'integer', 'min:1'],
          'name' => ['sometimes', 'string', 'max:255'],
          'enabled' => ['sometimes', 'boolean'],
          'guard' => ['sometimes', 'nullable', 'string', 'max:255'],
          'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
