<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\AuthorizesSsoAdmin;
use Illuminate\Foundation\Http\FormRequest;

final class ConnectionUpdateRequest extends FormRequest
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
          'identity_provider_id' => [
              'sometimes',
              static function (string $attribute, mixed $value, \Closure $fail): void {
                  if (is_int($value) && $value >= 1) {
                      return;
                  }

                  if (is_string($value) && $value !== '' && (TenantRouteKey::looksLikeUlid($value) || ctype_digit($value))) {
                      return;
                  }

                  $fail('The identity provider reference must be a numeric id or ULID.');
              },
          ],
          'name' => ['sometimes', 'string', 'max:255'],
          'enabled' => ['sometimes', 'boolean'],
          'guard' => ['sometimes', 'nullable', 'string', 'max:255'],
          'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
