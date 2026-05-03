<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcDiscovery;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Exceptions\OidcEndpointResolutionFailed;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Protocol\Oidc\Dto\OidcEndpoints;

final class DefaultOidcEndpointResolver implements OidcEndpointResolver
{
    public function __construct(
        private readonly OidcDiscovery $discovery,
        private readonly UrlTrustPolicy $urls,
    ) {
    }

    public function resolve(IdentityProvider $identityProvider): OidcEndpoints
    {
        $manual = $this->manualEndpoints($identityProvider);

        if ($manual instanceof OidcEndpoints) {
            return $manual;
        }

        if (!$this->discoveryEnabled($identityProvider)) {
            throw OidcEndpointResolutionFailed::missingConfig();
        }

        $doc = $this->discovery->discover($identityProvider);

        $this->urls->assertTrusted($doc->authorizationEndpoint, 'oidc.discovery.authorization_endpoint');
        $this->urls->assertTrusted($doc->tokenEndpoint, 'oidc.discovery.token_endpoint');
        $this->urls->assertTrusted($doc->jwksUri, 'oidc.discovery.jwks_uri');

        if ($doc->userinfoEndpoint !== null) {
            $this->urls->assertTrusted($doc->userinfoEndpoint, 'oidc.discovery.userinfo_endpoint');
        }

        return new OidcEndpoints(
            authorizationEndpoint: $doc->authorizationEndpoint,
            tokenEndpoint: $doc->tokenEndpoint,
            jwksUri: $doc->jwksUri,
            userinfoEndpoint: $doc->userinfoEndpoint,
            fromDiscovery: true,
        );
    }

    private function manualEndpoints(IdentityProvider $identityProvider): ?OidcEndpoints
    {
        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $endpoints = $config['endpoints'] ?? null;
        if (!is_array($endpoints)) {
            return null;
        }

        $authorization = $endpoints['authorization'] ?? null;
        $token = $endpoints['token'] ?? null;
        $jwks = $endpoints['jwks'] ?? null;
        $userinfo = $endpoints['userinfo'] ?? null;

        if (!is_string($authorization) || $authorization === '') {
            return null;
        }

        if (!is_string($token) || $token === '') {
            return null;
        }

        if (!is_string($jwks) || $jwks === '') {
            return null;
        }

        $userinfo = is_string($userinfo) && $userinfo !== '' ? $userinfo : null;

        $this->urls->assertTrusted($authorization, 'config.endpoints.authorization');
        $this->urls->assertTrusted($token, 'config.endpoints.token');
        $this->urls->assertTrusted($jwks, 'config.endpoints.jwks');

        if ($userinfo !== null) {
            $this->urls->assertTrusted($userinfo, 'config.endpoints.userinfo');
        }

        return new OidcEndpoints(
            authorizationEndpoint: $authorization,
            tokenEndpoint: $token,
            jwksUri: $jwks,
            userinfoEndpoint: $userinfo,
            fromDiscovery: false,
        );
    }

    private function discoveryEnabled(IdentityProvider $identityProvider): bool
    {
        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $flag = $config['discovery_enabled'] ?? null;
        if (is_bool($flag)) {
            return $flag;
        }

        $default = config('sso.oidc.discovery.enabled_default', true);

        return is_bool($default) ? $default : true;
    }
}
