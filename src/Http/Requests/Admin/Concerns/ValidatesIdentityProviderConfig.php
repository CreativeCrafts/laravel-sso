<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns;

use CreativeCrafts\LaravelSso\Contracts\Admin\IdentityProviderConfigValidator;
use Illuminate\Validation\Validator;

trait ValidatesIdentityProviderConfig
{
    /**
     * @param array<mixed> $input
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $input): array
    {
        return array_filter($input, static function ($key) {
            return is_string($key);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateIdentityProviderConfig(Validator $validator, string $protocol, array $config): void
    {
        $this->identityProviderConfigValidator()->validate($validator, $protocol, $config);
    }

    private function identityProviderConfigValidator(): IdentityProviderConfigValidator
    {
        return $this->container->make(IdentityProviderConfigValidator::class);
    }
}
