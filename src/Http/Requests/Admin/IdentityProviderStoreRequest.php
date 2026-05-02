<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin;

use CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns\ValidatesIdentityProviderConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class IdentityProviderStoreRequest extends FormRequest
{
    use ValidatesIdentityProviderConfig;

    public function authorize(): bool
    {
        $ability = config('sso.ui.gate', 'manageSso');
        $ability = is_string($ability) && $ability !== '' ? $ability : 'manageSso';

        return Gate::allows($ability);
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

            if ($protocol === 'oidc') {
                $this->validateOidcConfig($validator, $config);
            }

            if ($protocol === 'saml') {
                $this->validateSamlConfig($validator, $config);
            }
        });
    }
}
