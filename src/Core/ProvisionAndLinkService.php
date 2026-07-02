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
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Events\Dto\CallbackEventSummary;
use CreativeCrafts\LaravelSso\Events\IdentityLinked;
use CreativeCrafts\LaravelSso\Events\LoginCompleted;
use CreativeCrafts\LaravelSso\Events\UserProvisioned;
use CreativeCrafts\LaravelSso\Exceptions\EmailVerificationRequired;
use CreativeCrafts\LaravelSso\Exceptions\IdentityLinkDenied;
use CreativeCrafts\LaravelSso\Exceptions\MissingExternalSubject;
use CreativeCrafts\LaravelSso\Exceptions\ProvisioningDenied;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Exceptions\UserEmailAlreadyExists;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $persistedClaims = $this->persistedClaims($callback);

        if ($subject === '') {
            throw MissingExternalSubject::make();
        }

        $guard = $this->guards->selectGuard($tenant, $connection);

        /** @var Authenticatable $authenticatedUser */
        $authenticatedUser = DB::transaction(function () use ($tenant, $connection, $identityProvider, $subject, $email, $displayName, $claims, $persistedClaims, $guard, $eventCallback, $callback): Authenticatable {
            return $this->resolveAndAuthenticate(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                canonicalClaims: $callback->canonicalClaims,
                subject: $subject,
                email: $email,
                displayName: $displayName,
                claims: $claims,
                persistedClaims: $persistedClaims,
                guard: $guard,
                eventCallback: $eventCallback,
            );
        });

        return $authenticatedUser;
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $persistedClaims
     */
    private function resolveAndAuthenticate(
        Tenant $tenant,
        Connection $connection,
        IdentityProvider $identityProvider,
        Claims $canonicalClaims,
        string $subject,
        ?string $email,
        ?string $displayName,
        array $claims,
        array $persistedClaims,
        string $guard,
        CallbackEventSummary $eventCallback,
    ): Authenticatable {
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
                    claims: $persistedClaims,
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
            return $this->linkAndAuthenticateExistingUser(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                canonicalClaims: $canonicalClaims,
                subject: $subject,
                email: $email,
                displayName: $displayName,
                claims: $claims,
                persistedClaims: $persistedClaims,
                guard: $guard,
                eventCallback: $eventCallback,
                user: $user,
            );
        }

        if ($this->provisioningPolicy->allows($tenant, $connection, $identityProvider, $claims) === false) {
            throw ProvisioningDenied::make();
        }

        if (!is_string($email) || $email === '') {
            throw new RuntimeException('Cannot provision without an email claim.');
        }

        if (!$this->emailVerifiedForProvisioning($identityProvider, $canonicalClaims)) {
            throw EmailVerificationRequired::forProvisioning();
        }

        try {
            $user = $this->provisioner->provision($guard, $email, $displayName, $claims);
        } catch (UserEmailAlreadyExists) {
            $user = $this->users->findByEmail($guard, $email);

            if (!$user instanceof Authenticatable) {
                throw new RuntimeException('Cannot resolve existing user after email conflict.');
            }

            return $this->linkAndAuthenticateExistingUser(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                canonicalClaims: $canonicalClaims,
                subject: $subject,
                email: $email,
                displayName: $displayName,
                claims: $claims,
                persistedClaims: $persistedClaims,
                guard: $guard,
                eventCallback: $eventCallback,
                user: $user,
            );
        }

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
            claims: $persistedClaims,
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

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $persistedClaims
     */
    private function linkAndAuthenticateExistingUser(
        Tenant $tenant,
        Connection $connection,
        IdentityProvider $identityProvider,
        Claims $canonicalClaims,
        string $subject,
        ?string $email,
        ?string $displayName,
        array $claims,
        array $persistedClaims,
        string $guard,
        CallbackEventSummary $eventCallback,
        Authenticatable $user,
    ): Authenticatable {
        if (!$this->emailVerifiedForLinking($identityProvider, $canonicalClaims)) {
            throw EmailVerificationRequired::forLinking();
        }

        if ($this->identityLinkPolicy->allows($tenant, $connection, $identityProvider, $user, $claims) === false) {
            throw IdentityLinkDenied::make();
        }

        $externalIdentity = $this->externalIdentities->upsertForUser(
            tenant: $tenant,
            identityProvider: $identityProvider,
            subject: $subject,
            email: $email,
            displayName: $displayName,
            claims: $persistedClaims,
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

    /**
     * @return array<string, mixed>
     */
    private function persistedClaims(DriverCallbackResult $callback): array
    {
        if ((bool) config('sso.claims.persist_raw', false)) {
            $claims = $callback->claims;

            if (!(bool) config('sso.saml.persist_raw_saml', false)) {
                unset($claims['raw_saml']);
            }

            return $claims;
        }

        $claims = [
            'sub' => $callback->canonicalClaims->subject,
            'email' => $callback->canonicalClaims->email,
            'name' => $callback->canonicalClaims->displayName,
            'email_verified' => $callback->canonicalClaims->emailVerified,
        ];

        if ((bool) config('sso.claims.persist_groups', true)) {
            $claims['groups'] = array_slice(
                $callback->canonicalClaims->groups,
                0,
                $this->positiveIntConfig('sso.claims.max_group_items', 100),
            );
        }

        return array_filter(
            $claims,
            static fn (mixed $value): bool => $value !== null,
        );
    }

    private function positiveIntConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        return $default;
    }

    private function emailVerifiedForLinking(IdentityProvider $identityProvider, Claims $canonicalClaims): bool
    {
        if (!(bool) config('sso.linking.require_email_verified', true)) {
            return true;
        }

        return $this->emailClaimIsVerified($identityProvider, $canonicalClaims, 'linking');
    }

    private function emailVerifiedForProvisioning(IdentityProvider $identityProvider, Claims $canonicalClaims): bool
    {
        if (!(bool) config('sso.provisioning.require_email_verified', true)) {
            return true;
        }

        return $this->emailClaimIsVerified($identityProvider, $canonicalClaims, 'provisioning');
    }

    private function emailClaimIsVerified(IdentityProvider $identityProvider, Claims $canonicalClaims, string $context): bool
    {
        if ($canonicalClaims->emailVerified === true) {
            return true;
        }

        if ($identityProvider->protocol !== 'saml') {
            return false;
        }

        $configKey = $context === 'linking'
            ? 'sso.linking.trust_saml_email_attributes'
            : 'sso.provisioning.trust_saml_email_attributes';

        return (bool) config($configKey, false);
    }
}
