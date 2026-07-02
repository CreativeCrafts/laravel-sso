<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use RuntimeException;

final readonly class IdentityProviderRouteResolver
{
    public function __construct(
        private IdentityProviderRepository $identityProviders,
    ) {
    }

    public function resolveId(Tenant $tenant, string $routeKey): int
    {
        $identityProvider = $this->identityProviders->findForTenantByRouteKey($tenant, $routeKey);

        if (!$identityProvider instanceof IdentityProvider) {
            throw TenantScopedRecordNotFound::for(IdentityProvider::class, $routeKey);
        }

        return (int) $identityProvider->id;
    }

    public function publicRouteKey(IdentityProvider $identityProvider): string
    {
        $ulid = $identityProvider->ulid;

        if ($ulid === null || $ulid === '') {
            throw new RuntimeException('Identity provider is missing a public ULID.');
        }

        return $ulid;
    }
}
