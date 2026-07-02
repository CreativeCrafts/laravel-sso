<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use Illuminate\Http\Request;

final class SafeRedirectValidator
{
    public function sanitizeForStorage(?string $redirect, Request $request): ?string
    {
        if (!is_string($redirect) || $redirect === '') {
            return null;
        }

        $redirect = trim($redirect);
        $decodedRedirect = rawurldecode($redirect);

        if (
            $redirect === '' ||
            $decodedRedirect === '' ||
            $this->containsUnsafeRedirectCharacters($redirect) ||
            $this->containsUnsafeRedirectCharacters($decodedRedirect)
        ) {
            return null;
        }

        if ($this->isSafeLocalRedirectPath($redirect, $decodedRedirect)) {
            return $redirect;
        }

        if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $targetParts = parse_url($redirect);
        $currentParts = parse_url($request->getSchemeAndHttpHost());

        if (!is_array($targetParts) || !is_array($currentParts)) {
            return null;
        }

        return $this->isSameOrigin($targetParts, $currentParts) ? $redirect : null;
    }

    public function resolveStoredRedirect(Request $request, ?string $redirect): string
    {
        $sanitized = $this->sanitizeForStorage($redirect, $request);

        return $sanitized ?? '/';
    }

    private function containsUnsafeRedirectCharacters(string $redirect): bool
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $redirect) === 1) {
            return true;
        }

        return str_contains($redirect, '\\');
    }

    private function isSafeLocalRedirectPath(string $redirect, string $decodedRedirect): bool
    {
        if (!str_starts_with($redirect, '/')) {
            return false;
        }

        if (str_starts_with($redirect, '//')) {
            return false;
        }

        return !str_starts_with($decodedRedirect, '//');
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
