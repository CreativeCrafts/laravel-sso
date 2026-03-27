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
use CreativeCrafts\LaravelSso\Contracts\Repositories\ExternalIdentityRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Events\Dto\CallbackEventSummary;
use CreativeCrafts\LaravelSso\Events\IdentityLinked;
use CreativeCrafts\LaravelSso\Events\LoginCompleted;
use CreativeCrafts\LaravelSso\Events\UserProvisioned;
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
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use RuntimeException;

final readonly class ProvisionAndLinkService implements ProvisionAndLink
{
    public function __construct(
        private ConnectionRepository $connections,
        private IdentityProviderRepository $identityProviders,
        private ExternalIdentityRepository $externalIdentities,
        private GuardSelector $guards,
        private UserLocator $users,
        private UserProvisioner $provisioner,
        private ProvisioningPolicy $provisioningPolicy,
        private IdentityLinkPolicy $identityLinkPolicy,
        private AuthFactory $auth,
        private Dispatcher $events,
    ) {
    }

    public function handle(Request $request, Tenant $tenant, int $connectionId, DriverCallbackResult $callback): Authenticatable
    {
        if ($callback->authenticated === false) {
            throw new RuntimeException('Callback result is not authenticated.');
        }

        $connection = $this->connections->findForTenant($tenant, $connectionId);

        if (!$connection instanceof Connection) {
            throw TenantScopedRecordNotFound::for(Connection::class, $connectionId);
        }

        $identityProviderId = (int) $connection->identity_provider_id;

        $identityProvider = $this->identityProviders->findForTenant($tenant, $identityProviderId);

        if (!$identityProvider instanceof IdentityProvider) {
            throw TenantScopedRecordNotFound::for(IdentityProvider::class, $identityProviderId);
        }

        $eventCallback = CallbackEventSummary::fromResult(
            $identityProvider->protocol !== '' ? $identityProvider->protocol : 'unknown',
            $callback,
        );

        /** @var array<string, mixed> $claims */
        $claims = $callback->claims;

        $subject = $callback->canonicalClaims->subject;
        $email = $callback->canonicalClaims->email;
        $displayName = $callback->canonicalClaims->displayName;

        if ($subject === '') {
            throw MissingExternalSubject::make();
        }

        $guard = $this->guards->selectGuard($tenant, $connection);

        $existingExternal = $this->externalIdentities->findForTenantProviderAndSubject(
            tenant: $tenant,
            identityProvider: $identityProvider,
            subject: $subject,
        );

        if ($existingExternal instanceof ExternalIdentity) {
            $linked = $existingExternal->authenticatable;

            if ($linked instanceof Authenticatable) {
                $this->externalIdentities->upsertForUser(
                    tenant: $tenant,
                    identityProvider: $identityProvider,
                    subject: $subject,
                    email: $email,
                    displayName: $displayName,
                    claims: $claims,
                    user: $linked,
                );

                $this->auth->guard($guard)->login($linked);

                $this->events->dispatch(
                    new LoginCompleted(
                        tenant: $tenant,
                        connection: $connection,
                        identityProvider: $identityProvider,
                        user: $linked,
                        guard: $guard,
                        callback: $eventCallback,
                    ),
                );

                return $linked;
            }
        }

        $user = null;

        if (is_string($email) && $email !== '') {
            $user = $this->users->findByEmail($guard, $email);
        }

        if ($user instanceof Authenticatable) {
            if ($this->identityLinkPolicy->allows($tenant, $connection, $identityProvider, $user, $claims) === false) {
                throw IdentityLinkDenied::make();
            }

            $externalIdentity = $this->externalIdentities->upsertForUser(
                tenant: $tenant,
                identityProvider: $identityProvider,
                subject: $subject,
                email: $email,
                displayName: $displayName,
                claims: $claims,
                user: $user,
            );

            if ($externalIdentity->wasRecentlyCreated) {
                $this->events->dispatch(
                    new IdentityLinked(
                        tenant: $tenant,
                        connection: $connection,
                        identityProvider: $identityProvider,
                        externalIdentity: $externalIdentity,
                        user: $user,
                        guard: $guard,
                        callback: $eventCallback,
                    ),
                );
            }

            $this->auth->guard($guard)->login($user);

            $this->events->dispatch(
                new LoginCompleted(
                    tenant: $tenant,
                    connection: $connection,
                    identityProvider: $identityProvider,
                    user: $user,
                    guard: $guard,
                    callback: $eventCallback,
                ),
            );

            return $user;
        }

        if ($this->provisioningPolicy->allows($tenant, $connection, $identityProvider, $claims) === false) {
            throw ProvisioningDenied::make();
        }

        if (!is_string($email) || $email === '') {
            throw new RuntimeException('Cannot provision without an email claim.');
        }

        $user = $this->provisioner->provision($guard, $email, $displayName, $claims);

        $this->events->dispatch(
            new UserProvisioned(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                user: $user,
                guard: $guard,
                callback: $eventCallback,
            ),
        );

        $externalIdentity = $this->externalIdentities->upsertForUser(
            tenant: $tenant,
            identityProvider: $identityProvider,
            subject: $subject,
            email: $email,
            displayName: $displayName,
            claims: $claims,
            user: $user,
        );

        if ($externalIdentity->wasRecentlyCreated) {
            $this->events->dispatch(
                new IdentityLinked(
                    tenant: $tenant,
                    connection: $connection,
                    identityProvider: $identityProvider,
                    externalIdentity: $externalIdentity,
                    user: $user,
                    guard: $guard,
                    callback: $eventCallback,
                ),
            );
        }

        $this->auth->guard($guard)->login($user);

        $this->events->dispatch(
            new LoginCompleted(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                user: $user,
                guard: $guard,
                callback: $eventCallback,
            ),
        );

        return $user;
    }
}
