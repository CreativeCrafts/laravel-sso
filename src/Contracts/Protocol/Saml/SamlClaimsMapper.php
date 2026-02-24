<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Core\Dto\Claims;

interface SamlClaimsMapper
{
    /**
     * @param array<string, array<int, string>> $attributes
     */
    public function map(string $nameId, array $attributes): Claims;
}
