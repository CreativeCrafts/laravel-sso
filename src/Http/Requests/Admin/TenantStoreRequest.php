<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\AuthorizesSsoAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class TenantStoreRequest extends FormRequest
{
    use AuthorizesSsoAdmin;

    public function authorize(): bool
    {
        return $this->authorizeSsoAdmin();
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

    protected function prepareForValidation(): void
    {
        $ulid = $this->input('ulid');

        if (!is_string($ulid) || trim($ulid) === '') {
            $this->merge(['ulid' => (string) Str::ulid()]);
        }
    }
}
