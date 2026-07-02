<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Core\ConnectionRouteResolver;
use CreativeCrafts\LaravelSso\Http\Controllers\Concerns\HandlesCallbackResponse;
use Psr\Log\LoggerInterface;

final readonly class SamlAcsController
{
    use HandlesCallbackResponse;

    public function __construct(
        private TenantResolver $tenants,
        private HandleCallback $handleCallback,
        private ProvisionAndLink $provisionAndLink,
        private AuthAttemptRepository $authAttempts,
        private AuthAttemptService $authAttemptService,
        private ConnectionRouteResolver $connectionRoutes,
        private LoggerInterface $logger,
    ) {
    }
}
