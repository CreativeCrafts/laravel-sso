<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SamlAcsController
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
        $tenantModel = $this->tenants->resolve($request);

        if (!$tenantModel instanceof Tenant) {
            abort(404);
        }

        $connectionId = (int)$idp;

        $callbackResult = $this->handleCallback->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
        );

        if (!$callbackResult->authenticated) {
            return response('', 204);
        }

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

        $attempt = $this->authAttempts->findByState($tenantModel, $state);

        $redirectTo = '/';

        if ($attempt instanceof AuthAttempt) {
            $redirect = $attempt->redirect_to;

            if (is_string($redirect) && $redirect !== '') {
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

        $this->provisionAndLink->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
            callback: $callbackResult,
        );

        return redirect()->to($redirectTo);
    }
}
