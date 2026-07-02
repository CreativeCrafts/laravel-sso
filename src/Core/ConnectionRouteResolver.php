<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use RuntimeException;

final readonly class ConnectionRouteResolver
{
    public function __construct(
        private ConnectionRepository $connections,
    ) {
    }

    public function resolveId(Tenant $tenant, string $routeKey): int
    {
        $connection = $this->connections->findForTenantByRouteKey($tenant, $routeKey);

        if (!$connection instanceof Connection) {
            throw TenantScopedRecordNotFound::for(Connection::class, $routeKey);
        }

        return (int) $connection->id;
    }

    public function publicRouteKey(Connection $connection): string
    {
        $ulid = $connection->ulid;

        if ($ulid === null || $ulid === '') {
            throw new RuntimeException('Connection is missing a public ULID.');
        }

        return $ulid;
    }
}
