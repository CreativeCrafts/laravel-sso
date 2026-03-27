<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers\Concerns;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait HandlesCallbackResponse
{
    public function __invoke(Request $request, string $tenant, string $connection): Response
    {
        $tenantModel = $this->tenants->resolve($request);

        if (!$tenantModel instanceof Tenant) {
            abort(404);
        }

        $connectionId = (int) $connection;

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

        $redirectTo = $this->resolveRedirect($request, $attempt);

        $this->provisionAndLink->handle(
            request: $request,
            tenant: $tenantModel,
            connectionId: $connectionId,
            callback: $callbackResult,
        );

        return redirect()->to($redirectTo);
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

    private function resolveRedirect(Request $request, ?AuthAttempt $attempt): string
    {
        if (!$attempt instanceof AuthAttempt) {
            return '/';
        }

        $redirect = $attempt->redirect_to;

        if (!is_string($redirect) || $redirect === '') {
            return '/';
        }

        if (str_starts_with($redirect, '/')) {
            return $redirect;
        }

        if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
            return '/';
        }

        $targetParts = parse_url($redirect);
        $currentParts = parse_url($request->getSchemeAndHttpHost());

        if ($targetParts === false || $currentParts === false) {
            return '/';
        }

        return $this->isSameOrigin($targetParts, $currentParts) ? $redirect : '/';
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $current
     */
    private function isSameOrigin(array $target, array $current): bool
    {
        $targetSchemeRaw = $target['scheme'] ?? null;
        $currentSchemeRaw = $current['scheme'] ?? null;

        $targetScheme = is_string($targetSchemeRaw) ? strtolower($targetSchemeRaw) : '';
        $currentScheme = is_string($currentSchemeRaw) ? strtolower($currentSchemeRaw) : '';

        $targetHostRaw = $target['host'] ?? null;
        $currentHostRaw = $current['host'] ?? null;

        $targetHost = is_string($targetHostRaw) ? strtolower($targetHostRaw) : '';
        $currentHost = is_string($currentHostRaw) ? strtolower($currentHostRaw) : '';

        $targetPortRaw = $target['port'] ?? null;
        $currentPortRaw = $current['port'] ?? null;

        $targetPort = is_int($targetPortRaw) ? $targetPortRaw : $this->defaultPort($targetScheme);
        $currentPort = is_int($currentPortRaw) ? $currentPortRaw : $this->defaultPort($currentScheme);

        return $targetScheme !== ''
            && $currentScheme !== ''
            && $targetHost !== ''
            && $currentHost !== ''
            && $targetScheme === $currentScheme
            && $targetHost === $currentHost
            && $targetPort === $currentPort;
    }

    private function defaultPort(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }
}
