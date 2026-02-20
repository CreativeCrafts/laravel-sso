<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc\Dto;

use InvalidArgumentException;

final readonly class OidcDiscoveryDocument
{
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public ?string $userinfoEndpoint,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $issuer = self::string($payload, 'issuer');
        $authorization = self::string($payload, 'authorization_endpoint');
        $token = self::string($payload, 'token_endpoint');
        $jwks = self::string($payload, 'jwks_uri');

        $userinfo = $payload['userinfo_endpoint'] ?? null;
        $userinfo = is_string($userinfo) && $userinfo !== '' ? $userinfo : null;

        return new self(
            issuer: $issuer,
            authorizationEndpoint: $authorization,
            tokenEndpoint: $token,
            jwksUri: $jwks,
            userinfoEndpoint: $userinfo,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException("OIDC discovery field [{$key}] must be a non-empty string.");
        }

        return $value;
    }
}
