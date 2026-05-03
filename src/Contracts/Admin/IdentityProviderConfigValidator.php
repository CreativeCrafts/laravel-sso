<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Admin;

use Illuminate\Validation\Validator;

interface IdentityProviderConfigValidator
{
    /**
     * @param array<string, mixed> $config
     */
    public function validate(Validator $validator, string $protocol, array $config): void;
}
