<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\DriverRegistry;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuditLogRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Events\CallbackFailed;
use CreativeCrafts\LaravelSso\Events\CallbackSucceeded;
use CreativeCrafts\LaravelSso\Exceptions\CallbackStateMissing;
use CreativeCrafts\LaravelSso\Exceptions\InvalidAuthAttemptBinding;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Throwable;

final readonly class HandleCallbackService implements HandleCallback
{
    public function __construct(
        private ConnectionRepository $connections,
        private IdentityProviderRepository $identityProviders,
        private AuthAttemptService $attempts,
        private DriverRegistry $drivers,
        private AuditContextSanitizer $auditContexts,
        private AuditLogRepository $auditLogs,
        private Dispatcher $events,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(Request $request, Tenant $tenant, int $connectionId): DriverCallbackResult
    {
        $state = $this->extractState($request);
        $attempt = null;
        $connection = null;
        $identityProvider = null;
        $protocol = null;

        try {
            $attempt = $this->attempts->consumeByState($tenant, $state);

            if ($attempt->connection_id !== null && (int) $attempt->connection_id !== $connectionId) {
                throw InvalidAuthAttemptBinding::connectionMismatch(
                    expected: (int) $attempt->connection_id,
                    actual: $connectionId,
                );
            }

            $connection = $this->connections->findForTenant($tenant, $connectionId);

            if (!$connection instanceof Connection) {
                throw TenantScopedRecordNotFound::for(Connection::class, $connectionId);
            }

            $identityProviderId = (int) $connection->identity_provider_id;

            if ($attempt->identity_provider_id !== null && (int) $attempt->identity_provider_id !== $identityProviderId) {
                throw InvalidAuthAttemptBinding::identityProviderMismatch(
                    expected: (int) $attempt->identity_provider_id,
                    actual: $identityProviderId,
                );
            }

            $identityProvider = $this->identityProviders->findForTenant($tenant, $identityProviderId);

            if (!$identityProvider instanceof IdentityProvider) {
                throw TenantScopedRecordNotFound::for(IdentityProvider::class, $identityProviderId);
            }

            $protocol = $identityProvider->protocol !== '' ? $identityProvider->protocol : $attempt->protocol;

            $driver = $this->drivers->get($protocol);

            $result = $driver->handleCallback($request, $tenant, $connection, $attempt);

            $this->auditSucceeded(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                authAttemptId: $attempt->id,
                context: $this->auditContexts->sanitizeSuccess($protocol, $result),
            );

            $this->events->dispatch(new CallbackSucceeded(
                request: $request,
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                attempt: $attempt,
                result: $result,
            ));

            return $result;
        } catch (Throwable $e) {
            $this->auditFailed(
                tenant: $tenant,
                connectionId: $connectionId,
                identityProviderId: $identityProvider?->id,
                authAttemptId: $attempt?->id,
                context: $this->auditContexts->sanitizeFailure(
                    exception: $e,
                    state: $state,
                    protocol: $protocol ?? $attempt?->protocol,
                ),
            );

            $this->events->dispatch(new CallbackFailed(
                request: $request,
                tenant: $tenant,
                connectionId: $connectionId,
                attempt: $attempt,
                connection: $connection,
                identityProvider: $identityProvider,
                protocol: $protocol ?? $attempt?->protocol,
                exception: $e,
            ));

            throw $e;
        }
    }

    private function extractState(Request $request): string
    {
        $candidates = [
            $request->query('state'),
            $request->input('state'),
            $request->input('RelayState'),
            $request->input('relay_state'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw CallbackStateMissing::make();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function auditSucceeded(
        Tenant $tenant,
        Connection $connection,
        IdentityProvider $identityProvider,
        int $authAttemptId,
        array $context,
    ): void {
        $this->auditLogs->create([
            'tenant_id' => $tenant->id,
            'identity_provider_id' => $identityProvider->id,
            'connection_id' => $connection->id,
            'auth_attempt_id' => $authAttemptId,
            'event' => 'sso.callback.succeeded',
            'level' => 'info',
            'context' => $context,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function auditFailed(
        Tenant $tenant,
        int $connectionId,
        ?int $identityProviderId,
        ?int $authAttemptId,
        array $context,
    ): void {
        $this->auditLogs->create([
            'tenant_id' => $tenant->id,
            'identity_provider_id' => $identityProviderId,
            'connection_id' => $connectionId,
            'auth_attempt_id' => $authAttemptId,
            'event' => 'sso.callback.failed',
            'level' => 'warning',
            'context' => $context,
        ]);
    }
}
