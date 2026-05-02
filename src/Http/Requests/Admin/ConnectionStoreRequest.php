<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConnectionStoreRequest extends FormRequest
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
          'identity_provider_id' => ['required', 'integer', 'min:1'],
          'name' => ['required', 'string', 'max:255'],
          'enabled' => ['sometimes', 'boolean'],
          'guard' => ['nullable', 'string', 'max:255'],
          'settings' => ['nullable', 'array'],
        ];
    }
}
