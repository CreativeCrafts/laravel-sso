<?php

declare(strict_types=1);


if (!function_exists('sso_redirect_url')) {
    function sso_redirect_url(string $tenantUlid, int|string $connectionId): string
    {
        return route('sso.redirect', [
            'tenant' => $tenantUlid,
            'connection' => (string) $connectionId,
        ], absolute: true);
    }
}
