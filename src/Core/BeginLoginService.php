<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\BeginLogin;
use CreativeCrafts\LaravelSso\Contracts\Core\DriverRegistry;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Events\AuthAttemptCreated;
use CreativeCrafts\LaravelSso\Events\BeginLoginRedirectGenerated;
use CreativeCrafts\LaravelSso\Events\BeginLoginRequested;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class BeginLoginService implements BeginLogin
{
    public function __construct(
        private ConnectionRepository $connections,
        private IdentityProviderRepository $identityProviders,
        private AuthAttemptService $attempts,
        private DriverRegistry $drivers,
        private Dispatcher $events,
    ) {
    }

    public function handle(Request $request, Tenant $tenant, int $connectionId): DriverStartResult
    {
        $connection = $this->connections->findForTenant($tenant, $connectionId);

        if (!$connection instanceof Connection) {
            throw TenantScopedRecordNotFound::for(Connection::class, $connectionId);
        }

        $identityProviderId = (int)$connection->identity_provider_id;

        $identityProvider = $this->identityProviders->findForTenant($tenant, $identityProviderId);

        if (!$identityProvider instanceof IdentityProvider) {
            throw TenantScopedRecordNotFound::for(IdentityProvider::class, $identityProviderId);
        }

        $redirectTo = $request->query('redirect_to');
        $redirectTo = is_string($redirectTo) && $redirectTo !== '' ? $redirectTo : null;

        $this->events->dispatch(
            new BeginLoginRequested(
                request: $request,
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                redirectTo: $redirectTo,
            ),
        );

        $protocol = $identityProvider->protocol;
        $protocol = $protocol !== '' ? $protocol : 'oidc';

        $codeVerifier = null;
        $withNonce = false;

        if ($protocol === 'oidc') {
            $withNonce = true;

            $len = config('sso.attempts.code_verifier_length', 96);
            $len = is_int($len) && $len >= 32 ? $len : 96;

            $codeVerifier = Str::random($len);
        }

        $attempt = $this->attempts->create(
            tenant: $tenant,
            protocol: $protocol,
            connection: $connection,
            identityProvider: $identityProvider,
            redirectTo: $redirectTo,
            codeVerifier: $codeVerifier,
            withNonce: $withNonce,
        );

        $this->events->dispatch(
            new AuthAttemptCreated(
                request: $request,
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                attempt: $attempt,
            ),
        );

        $driver = $this->drivers->get($protocol);

        $result = $driver->start($request, $tenant, $connection, $attempt);

        $this->events->dispatch(
            new BeginLoginRedirectGenerated(
                request: $request,
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                attempt: $attempt,
                result: $result,
            ),
        );

        return $result;
    }
}
