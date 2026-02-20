<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Protocol\Oidc\Dto\OidcEndpoints;

interface OidcEndpointResolver
{
    public function resolve(IdentityProvider $identityProvider): OidcEndpoints;
}
