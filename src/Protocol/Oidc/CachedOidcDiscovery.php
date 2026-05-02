<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcDiscovery;
use CreativeCrafts\LaravelSso\Exceptions\OidcDiscoveryFailed;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Protocol\Oidc\Dto\OidcDiscoveryDocument;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

final class CachedOidcDiscovery implements OidcDiscovery
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly UrlTrustPolicy $urls,
    ) {
    }

    public function discover(IdentityProvider $identityProvider): OidcDiscoveryDocument
    {
        $url = $this->resolveDiscoveryUrl($identityProvider);
        $this->urls->assertTrusted($url, 'config.discovery_url');

        $ttlSeconds = $this->ttlSeconds();

        return $this->cache->remember($this->cacheKey($url), $ttlSeconds, function () use ($url): OidcDiscoveryDocument {
            try {
                $timeout = $this->timeoutSeconds();

                $response = $this->http
                  ->timeout($timeout)
                  ->acceptJson()
                  ->get($url);

                if (!$response->successful()) {
                    throw OidcDiscoveryFailed::forUrl($url);
                }

                $json = $response->json();

                if (!is_array($json)) {
                    throw OidcDiscoveryFailed::forUrl($url);
                }

                /** @var array<string, mixed> $payload */
                $payload = $json;

                return OidcDiscoveryDocument::fromArray($payload);
            } catch (Throwable $e) {
                throw OidcDiscoveryFailed::forUrl($url, $e);
            }
        });
    }

    private function resolveDiscoveryUrl(IdentityProvider $identityProvider): string
    {
        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $explicit = $config['discovery_url'] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $issuer = $config['issuer'] ?? null;
        if (is_string($issuer) && $issuer !== '') {
            return rtrim($issuer, '/') . '/.well-known/openid-configuration';
        }

        throw OidcDiscoveryFailed::forUrl('unknown');
    }

    private function ttlSeconds(): int
    {
        $value = config('sso.oidc.discovery.cache_ttl_seconds', 3600);

        return is_int($value) && $value > 0 ? $value : 3600;
    }

    private function cacheKey(string $url): string
    {
        return 'sso:oidc:discovery:' . sha1($url);
    }

    private function timeoutSeconds(): int
    {
        $value = config('sso.oidc.discovery.http_timeout_seconds', 10);

        return is_int($value) && $value > 0 ? $value : 10;
    }
}
