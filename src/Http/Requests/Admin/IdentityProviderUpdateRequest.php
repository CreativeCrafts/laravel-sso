<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\AuthorizesSsoAdmin;
use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\ValidatesIdentityProviderConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class IdentityProviderUpdateRequest extends FormRequest
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
          'name' => ['sometimes', 'string', 'max:255'],
          'protocol' => ['sometimes', 'string', 'in:oidc,saml'],
          'enabled' => ['sometimes', 'boolean'],
          'config' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $protocolRaw = $this->input('protocol');
            $configProvided = $this->has('config');

            if (!$configProvided) {
                return;
            }

            if (!is_string($protocolRaw) || $protocolRaw === '') {
                $validator->errors()->add('protocol', 'The protocol field is required when config is being updated.');
                return;
            }

            $configRaw = $this->input('config');

            /** @var array<string, mixed> $config */
            $config = is_array($configRaw) ? $this->stringKeyedArray($configRaw) : [];

            $this->validateIdentityProviderConfig($validator, $protocolRaw, $config);
        });
    }
}
