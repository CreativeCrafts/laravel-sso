<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\DriverRegistry;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Exceptions\CallbackStateMissing;
use CreativeCrafts\LaravelSso\Exceptions\InvalidAuthAttemptBinding;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\AuditLog;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Throwable;

final class HandleCallbackService implements HandleCallback
{
    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly IdentityProviderRepository $identityProviders,
        private readonly AuthAttemptService $attempts,
        private readonly DriverRegistry $drivers,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(Request $request, Tenant $tenant, int $connectionId): DriverCallbackResult
    {
        $state = $this->extractState($request);

        try {
            $attempt = $this->attempts->consumeByState($tenant, $state);

            if ($attempt->connection_id !== null && (int)$attempt->connection_id !== $connectionId) {
                throw InvalidAuthAttemptBinding::connectionMismatch(
                    expected: (int)$attempt->connection_id,
                    actual: $connectionId,
                );
            }

            $connection = $this->connections->findForTenant($tenant, $connectionId);

            if (!$connection instanceof Connection) {
                throw TenantScopedRecordNotFound::for(Connection::class, $connectionId);
            }

            $identityProviderId = (int)$connection->identity_provider_id;

            if ($attempt->identity_provider_id !== null && (int)$attempt->identity_provider_id !== $identityProviderId) {
                throw InvalidAuthAttemptBinding::identityProviderMismatch(
                    expected: (int)$attempt->identity_provider_id,
                    actual: $identityProviderId,
                );
            }

            $identityProvider = $this->identityProviders->findForTenant($tenant, $identityProviderId);

            if (!$identityProvider instanceof IdentityProvider) {
                throw TenantScopedRecordNotFound::for(IdentityProvider::class, $identityProviderId);
            }

            $protocol = $identityProvider->protocol;
            $protocol = $protocol !== '' ? $protocol : $attempt->protocol;

            $driver = $this->drivers->get($protocol);

            $result = $driver->handleCallback($request, $tenant, $connection, $attempt);

            $this->auditSucceeded(
                tenant: $tenant,
                connection: $connection,
                identityProvider: $identityProvider,
                authAttemptId: $attempt->id,
                protocol: $protocol,
                authenticated: $result->authenticated,
                context: [
                'error' => $result->error,
                'driver_context' => $result->context,
              ],
            );

            return $result;
        } catch (Throwable $e) {
            $this->auditFailed(
                tenant: $tenant,
                connectionId: $connectionId,
                state: $state,
                exception: $e,
            );

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
        string $protocol,
        bool $authenticated,
        array $context,
    ): void {
        AuditLog::query()->create([
          'tenant_id' => $tenant->id,
          'identity_provider_id' => $identityProvider->id,
          'connection_id' => $connection->id,
          'auth_attempt_id' => $authAttemptId,
          'event' => 'sso.callback.succeeded',
          'level' => 'info',
          'context' => array_merge($context, [
            'protocol' => $protocol,
            'authenticated' => $authenticated,
          ]),
        ]);
    }

    private function auditFailed(
        Tenant $tenant,
        int $connectionId,
        string $state,
        Throwable $exception,
    ): void {
        AuditLog::query()->create([
          'tenant_id' => $tenant->id,
          'identity_provider_id' => null,
          'connection_id' => $connectionId,
          'auth_attempt_id' => null,
          'event' => 'sso.callback.failed',
          'level' => 'warning',
          'context' => [
            'state' => $state,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
          ],
        ]);
    }
}
