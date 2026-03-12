<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

interface ExternalIdentityRepository
{
    public function findForTenantIdentityProviderAndSubject(
        Tenant $tenant,
        IdentityProvider $identityProvider,
        string $subject,
    ): ?ExternalIdentity;

    /**
     * @param array<string, mixed> $claims
     */
    public function upsertForUser(
        Tenant $tenant,
        IdentityProvider $identityProvider,
        string $subject,
        ?string $email,
        ?string $displayName,
        array $claims,
        Authenticatable $user,
    ): ExternalIdentity;
}
