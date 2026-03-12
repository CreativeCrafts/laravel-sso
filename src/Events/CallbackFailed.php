<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Events;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Throwable;

final readonly class CallbackFailed
{
    public function __construct(
        public Request $request,
        public Tenant $tenant,
        public int $connectionId,
        public ?AuthAttempt $attempt,
        public ?Connection $connection,
        public ?IdentityProvider $identityProvider,
        public ?string $protocol,
        public Throwable $exception,
    ) {
    }
}
