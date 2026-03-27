<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Events;

use CreativeCrafts\LaravelSso\Events\Dto\CallbackEventSummary;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class IdentityLinked
{
    public function __construct(
        public Tenant $tenant,
        public Connection $connection,
        public IdentityProvider $identityProvider,
        public ExternalIdentity $externalIdentity,
        public Authenticatable $user,
        public string $guard,
        public CallbackEventSummary $callback,
    ) {
    }
}
