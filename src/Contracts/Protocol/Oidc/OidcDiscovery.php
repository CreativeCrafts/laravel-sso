<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Protocol\Oidc\Dto\OidcDiscoveryDocument;

interface OidcDiscovery
{
    public function discover(IdentityProvider $identityProvider): OidcDiscoveryDocument;
}
