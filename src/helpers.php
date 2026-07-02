<?php

declare(strict_types=1);


if (!function_exists('sso_redirect_url')) {
    function sso_redirect_url(string $tenantUlid, int|string $connectionId, ?string $redirectTo = null): string
    {
        if (!config('sso.routes.enabled', true)) {
            return '';
        }

        if (!app('router')->has('sso.redirect')) {
            return '';
        }

        $parameters = [
            'tenant' => $tenantUlid,
            'connection' => (string) $connectionId,
        ];

        $url = route('sso.redirect', $parameters, absolute: true);

        if ($redirectTo !== null && $redirectTo !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'redirect_to=' . rawurlencode($redirectTo);
        }

        return $url;
    }
}
