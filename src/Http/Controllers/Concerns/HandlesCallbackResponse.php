<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Concerns;

use CreativeCrafts\LaravelSso\Core\SafeRedirectValidator;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

trait HandlesCallbackResponse
{
    public function __invoke(Request $request, string $tenant, string $connection): Response
    {
        $tenantModel = $this->tenants->resolve($request);

        if (!$tenantModel instanceof Tenant) {
            abort(404);
        }

        $connectionId = $this->connectionRoutes->resolveId($tenantModel, $connection);

        $callbackResult = $this->handleCallback->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
        );

        if (!$callbackResult->authenticated) {
            return response('', 204);
        }

        $state = $this->extractState($request);

        if ($state === null) {
            return response('', 204);
        }

        $attempt = $this->authAttempts->findByState($tenantModel, $state);

        $redirectTo = $this->redirectValidator()->resolveStoredRedirect(
            $request,
            $attempt?->redirect_to,
        );

        try {
            $this->provisionAndLink->handle(
                request: $request,
                tenant: $tenantModel,
                connectionId: $connectionId,
                callback: $callbackResult,
            );

            if ($attempt instanceof AuthAttempt) {
                $this->authAttemptService->markConsumed($attempt);
            }
        } catch (Throwable $exception) {
            if ($attempt instanceof AuthAttempt) {
                try {
                    $this->authAttemptService->markConsumed($attempt);
                } catch (Throwable $markConsumedException) {
                    $this->ssoLogger()->error('SSO auth attempt terminal consume failed', [
                        'exception' => $markConsumedException->getMessage(),
                    ]);
                }
            }

            throw $exception;
        }

        return redirect()->to($redirectTo);
    }

    private function redirectValidator(): SafeRedirectValidator
    {
        return app(SafeRedirectValidator::class);
    }

    private function ssoLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private function extractState(Request $request): ?string
    {
        $stateCandidates = [
            $request->query('state'),
            $request->input('state'),
            $request->input('RelayState'),
            $request->input('relay_state'),
        ];

        foreach ($stateCandidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
