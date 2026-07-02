<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

final class TenantRouteKey
{
    public static function looksLikeUlid(string $key): bool
    {
        return strlen($key) === 26 && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $key) === 1;
    }

    public static function normalizeForStorage(string $ulid): string
    {
        $ulid = trim($ulid);

        if ($ulid === '') {
            return $ulid;
        }

        return self::looksLikeUlid($ulid) ? strtoupper($ulid) : $ulid;
    }

    public static function isNumericId(string $key): bool
    {
        return $key !== '' && ctype_digit($key);
    }
}
