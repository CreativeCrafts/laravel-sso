<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Events;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class AuthAttemptCreated
{
    public function __construct(
        public Request $request,
        public Tenant $tenant,
        public Connection $connection,
        public IdentityProvider $identityProvider,
        public AuthAttempt $attempt,
    ) {
    }
}
