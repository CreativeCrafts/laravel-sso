<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\GuardSelector;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\UserLocator;
use CreativeCrafts\LaravelSso\Contracts\Core\UserProvisioner;
use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Exceptions\IdentityLinkDenied;
use CreativeCrafts\LaravelSso\Exceptions\MissingExternalSubject;
use CreativeCrafts\LaravelSso\Exceptions\ProvisioningDenied;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use RuntimeException;

final readonly class ProvisionAndLinkService implements ProvisionAndLink
{
    public function __construct(
        private ConnectionRepository $connections,
        private IdentityProviderRepository $identityProviders,
        private GuardSelector $guards,
        private UserLocator $users,
        private UserProvisioner $provisioner,
        private ProvisioningPolicy $provisioningPolicy,
        private IdentityLinkPolicy $identityLinkPolicy,
        private AuthFactory $auth,
    ) {
    }

    public function handle(Request $request, Tenant $tenant, int $connectionId, DriverCallbackResult $callback): Authenticatable
    {
        if (!$callback->authenticated) {
            throw new RuntimeException('Callback result is not authenticated.');
        }

        $connection = $this->connections->findForTenant($tenant, $connectionId);

        if (!$connection instanceof Connection) {
            throw TenantScopedRecordNotFound::for(Connection::class, $connectionId);
        }

        $identityProviderId = (int)$connection->identity_provider_id;

        $identityProvider = $this->identityProviders->findForTenant($tenant, $identityProviderId);

        if (!$identityProvider instanceof IdentityProvider) {
            throw TenantScopedRecordNotFound::for(IdentityProvider::class, $identityProviderId);
        }

        /** @var array<string, mixed> $claims */
        $claims = $callback->claims;

        $subject = $this->extractSubject($callback, $claims);
        $email = $this->extractEmail($callback, $claims);
        $displayName = $this->extractDisplayName($callback, $claims);

        $guard = $this->guards->selectGuard($tenant, $connection);

        $existingExternal = ExternalIdentity::query()
          ->where('tenant_id', $tenant->id)
          ->where('identity_provider_id', $identityProvider->id)
          ->where('provider_subject', $subject)
          ->first();

        if ($existingExternal instanceof ExternalIdentity) {
            $linked = $existingExternal->authenticatable;

            if ($linked instanceof Authenticatable) {
                $this->upsertExternalIdentityForUser(
                    tenant: $tenant,
                    identityProvider: $identityProvider,
                    subject: $subject,
                    email: $email,
                    displayName: $displayName,
                    claims: $claims,
                    user: $linked,
                );

                $this->auth->guard($guard)->login($linked);

                return $linked;
            }
        }

        $user = null;

        if (is_string($email) && $email !== '') {
            $user = $this->users->findByEmail($guard, $email);
        }

        if ($user instanceof Authenticatable) {
            if (!$this->identityLinkPolicy->allows($tenant, $connection, $identityProvider, $user, $claims)) {
                throw IdentityLinkDenied::make();
            }

            $this->upsertExternalIdentityForUser(
                tenant: $tenant,
                identityProvider: $identityProvider,
                subject: $subject,
                email: $email,
                displayName: $displayName,
                claims: $claims,
                user: $user,
            );

            $this->auth->guard($guard)->login($user);

            return $user;
        }

        if (!$this->provisioningPolicy->allows($tenant, $connection, $identityProvider, $claims)) {
            throw ProvisioningDenied::make();
        }

        if (!is_string($email) || $email === '') {
            throw new RuntimeException('Cannot provision without an email claim.');
        }

        $user = $this->provisioner->provision($guard, $email, $displayName, $claims);

        $this->upsertExternalIdentityForUser(
            tenant: $tenant,
            identityProvider: $identityProvider,
            subject: $subject,
            email: $email,
            displayName: $displayName,
            claims: $claims,
            user: $user,
        );

        $this->auth->guard($guard)->login($user);

        return $user;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function extractSubject(DriverCallbackResult $callback, array $claims): string
    {
        if (is_string($callback->subject) && $callback->subject !== '') {
            return $callback->subject;
        }

        $sub = $claims['sub'] ?? null;

        if (is_string($sub) && $sub !== '') {
            return $sub;
        }

        $nameId = $claims['name_id'] ?? null;

        if (is_string($nameId) && $nameId !== '') {
            return $nameId;
        }

        throw MissingExternalSubject::make();
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function extractEmail(DriverCallbackResult $callback, array $claims): ?string
    {
        if (is_string($callback->email) && $callback->email !== '') {
            return $callback->email;
        }

        $email = $claims['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function extractDisplayName(DriverCallbackResult $callback, array $claims): ?string
    {
        if (is_string($callback->displayName) && $callback->displayName !== '') {
            return $callback->displayName;
        }

        $name = $claims['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function upsertExternalIdentityForUser(
        Tenant $tenant,
        IdentityProvider $identityProvider,
        string $subject,
        ?string $email,
        ?string $displayName,
        array $claims,
        Authenticatable $user,
    ): void {
        $authIdentifier = $user->getAuthIdentifier();

        if (is_int($authIdentifier)) {
            $authenticatableId = (string)$authIdentifier;
        } elseif (is_string($authIdentifier) && $authIdentifier !== '') {
            $authenticatableId = $authIdentifier;
        } else {
            throw new RuntimeException('Authenticatable identifier must be a non-empty string or int.');
        }

        ExternalIdentity::query()->updateOrCreate(
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
