<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Contracts\Core\BeginLogin;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SsoRedirectController
{
    public function __construct(
        private TenantResolver $tenants,
        private BeginLogin $beginLogin,
    ) {
    }

    public function __invoke(Request $request, string $tenant, string $connection): Response
    {
        $tenantModel = $this->tenants->resolve($request);

        if (!$tenantModel instanceof Tenant) {
            abort(404);
        }

        $connectionId = (int) $connection;

        $result = $this->beginLogin->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
        );

        return redirect()->away($result->redirectUrl);
    }
}
