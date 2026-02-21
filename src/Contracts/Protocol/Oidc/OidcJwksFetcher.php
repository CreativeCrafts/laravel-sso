<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Models\IdentityProvider;

interface OidcJwksFetcher
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchKeys(IdentityProvider $identityProvider): array;
}
