<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;

interface OidcIdTokenValidator
{
    /**
     * @return array<string, mixed> Validated claims
     */
    public function validate(IdentityProvider $identityProvider, AuthAttempt $attempt, string $idToken): array;
}
