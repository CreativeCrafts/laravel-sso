<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\AuthorizesSsoAdmin;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\Rules\UniqueTenantUlid;
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
          'ulid' => ['required', 'string', 'max:64', new UniqueTenantUlid()],
          'name' => ['nullable', 'string', 'max:255'],
          'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ulid = $this->input('ulid');

        if (!is_string($ulid) || trim($ulid) === '') {
            $ulid = (string) Str::ulid();
        }

        $this->merge(['ulid' => TenantRouteKey::normalizeForStorage($ulid)]);
    }
}
