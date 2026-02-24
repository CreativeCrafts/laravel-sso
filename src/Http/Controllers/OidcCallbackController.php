<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class OidcCallbackController
{
    public function __construct(
        private TenantResolver $tenants,
        private HandleCallback $handleCallback,
    ) {
    }

    public function __invoke(Request $request, string $tenant, string $idp): Response
    {
        $tenantModel = $this->tenants->resolve($request);

        if (!$tenantModel instanceof Tenant) {
            abort(404);
        }

        $connectionId = (int)$idp;

        $this->handleCallback->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
        );

        return response('', 204);
    }
}
