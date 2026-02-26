<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ConnectionStoreRequest extends FormRequest
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
        return [
          'identity_provider_id' => ['required', 'integer', 'min:1'],
          'name' => ['required', 'string', 'max:255'],
          'enabled' => ['sometimes', 'boolean'],
          'guard' => ['nullable', 'string', 'max:255'],
          'settings' => ['nullable', 'array'],
        ];
    }
}
