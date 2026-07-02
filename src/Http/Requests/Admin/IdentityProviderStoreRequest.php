<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\AuthorizesSsoAdmin;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\ValidatesIdentityProviderConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class IdentityProviderStoreRequest extends FormRequest
{
    use AuthorizesSsoAdmin;
    use ValidatesIdentityProviderConfig;

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
          'name' => ['required', 'string', 'max:255'],
          'protocol' => ['required', 'string', 'in:oidc,saml'],
          'enabled' => ['sometimes', 'boolean'],
          'config' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $protocolRaw = $this->input('protocol');
            $configRaw = $this->input('config');

            $protocol = is_string($protocolRaw) ? $protocolRaw : '';

            /** @var array<string, mixed> $config */
            $config = is_array($configRaw) ? $this->stringKeyedArray($configRaw) : [];

            $this->validateIdentityProviderConfig($validator, $protocol, $config);
        });
    }
}
