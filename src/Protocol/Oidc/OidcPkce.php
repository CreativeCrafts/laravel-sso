<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc;

final class OidcPkce
{
    public static function codeChallengeS256(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);

        return self::base64UrlEncode($hash);
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
