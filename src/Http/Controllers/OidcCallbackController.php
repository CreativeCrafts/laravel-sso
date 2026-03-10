<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class OidcCallbackController
{
    public function __construct(
        private TenantResolver $tenants,
        private HandleCallback $handleCallback,
        private ProvisionAndLink $provisionAndLink,
        private AuthAttemptRepository $authAttempts,
    ) {
    }

    public function __invoke(Request $request, string $tenant, string $idp): Response
    {
        // Resolve the tenant from the current request. If no tenant is found, abort with a 404.
        $tenantModel = $this->tenants->resolve($request);
        if (!$tenantModel instanceof Tenant) {
            abort(404);
        }

        // Cast the route parameter to an integer connection identifier.
        $connectionId = (int)$idp;

        // Handle the protocol-specific callback via the core service.
        $callbackResult = $this->handleCallback->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
        );

        // If the result indicates the user is not authenticated, return an empty 204 response.
        if (!$callbackResult->authenticated) {
            return response('', 204);
        }

        // Extract the state value from possible locations.
        $stateCandidates = [
          $request->query('state'),
          $request->input('state'),
          $request->input('RelayState'),
          $request->input('relay_state'),
        ];
        $state = null;
        foreach ($stateCandidates as $value) {
            if (is_string($value) && $value !== '') {
                $state = $value;
                break;
            }
        }
        if ($state === null) {
            return response('', 204);
        }

        // Look up the auth attempt via the repository. The attempt may be consumed but still present.
        $attempt = $this->authAttempts->findByState($tenantModel, $state);

        // Default redirect to the root path if no valid redirect is provided.
        $redirectTo = '/';
        if ($attempt !== null) {
            $redirect = $attempt->redirect_to;
            if (is_string($redirect) && $redirect !== '') {
                // Prevent open redirects: allow only relative URLs or same-host absolute URLs.
                if (str_starts_with($redirect, '/')) {
                    $redirectTo = $redirect;
                } elseif (filter_var($redirect, FILTER_VALIDATE_URL) !== false) {
                    $currentHost = $request->getSchemeAndHttpHost();
                    if (str_starts_with($redirect, $currentHost)) {
                        $redirectTo = $redirect;
                    }
                }
            }
        }

        // Provision and/or link the user, logging them into the appropriate guard.
        $this->provisionAndLink->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
            callback: $callbackResult,
        );

        // Redirect the authenticated user to the intended location.
        return redirect()->to($redirectTo);
    }
}
