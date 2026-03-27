<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Http\Controllers\Concerns\HandlesCallbackResponse;

final readonly class OidcCallbackController
{
    use HandlesCallbackResponse;

    public function __construct(
        private TenantResolver $tenants,
        private HandleCallback $handleCallback,
        private ProvisionAndLink $provisionAndLink,
        private AuthAttemptRepository $authAttempts,
    ) {
    }
}
