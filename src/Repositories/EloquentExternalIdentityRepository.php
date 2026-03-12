<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\ExternalIdentityRepository;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class EloquentExternalIdentityRepository implements ExternalIdentityRepository
{
    public function findForTenantIdentityProviderAndSubject(
        Tenant $tenant,
        IdentityProvider $identityProvider,
        string $subject,
    ): ?ExternalIdentity {
        return ExternalIdentity::query()
            ->where('tenant_id', $tenant->id)
            ->where('identity_provider_id', $identityProvider->id)
            ->where('provider_subject', $subject)
            ->first();
    }

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
    ): ExternalIdentity {
        $authIdentifier = $user->getAuthIdentifier();

        if (is_int($authIdentifier)) {
            $authenticatableId = (string) $authIdentifier;
        } elseif (is_string($authIdentifier) && $authIdentifier !== '') {
            $authenticatableId = $authIdentifier;
        } else {
            throw new RuntimeException('Authenticatable identifier must be a non-empty string or int.');
        }

        return ExternalIdentity::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'identity_provider_id' => $identityProvider->id,
                'provider_subject' => $subject,
            ],
            [
                'email' => $email,
                'display_name' => $displayName,
                'claims' => $claims,
                'authenticatable_type' => $user::class,
                'authenticatable_id' => $authenticatableId,
            ],
        );
    }
}
