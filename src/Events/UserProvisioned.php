<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Events;

use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

final readonly class UserProvisioned
{
    public function __construct(
        public Request $request,
        public Tenant $tenant,
        public Connection $connection,
        public IdentityProvider $identityProvider,
        public Authenticatable $user,
        public string $guard,
        public DriverCallbackResult $callback,
    ) {
    }
}
