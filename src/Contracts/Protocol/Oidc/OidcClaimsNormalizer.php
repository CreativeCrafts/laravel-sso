<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Core\Dto\Claims;

interface OidcClaimsNormalizer
{
    /**
     * @param array<string, mixed> $claims
     */
    public function normalize(array $claims): Claims;
}
