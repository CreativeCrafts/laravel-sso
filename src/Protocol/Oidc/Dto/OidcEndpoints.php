<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc\Dto;

final readonly class OidcEndpoints
{
    public function __construct(
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public ?string $userinfoEndpoint,
        public bool $fromDiscovery,
    ) {
    }
}
