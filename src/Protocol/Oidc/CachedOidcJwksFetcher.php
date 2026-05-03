<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Contracts\Core\IdpOutboundUrlPolicy;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher;
use CreativeCrafts\LaravelSso\Exceptions\OidcJwksFetchFailed;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

final class CachedOidcJwksFetcher implements OidcJwksFetcher
{
    public function __construct(
        private readonly OidcEndpointResolver $endpoints,
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly IdpOutboundUrlPolicy $outboundUrls,
    ) {
    }

    public function fetchKeys(IdentityProvider $identityProvider): array
    {
        $ep = $this->endpoints->resolve($identityProvider);
        $url = $ep->jwksUri;

        $ttl = $this->ttlSeconds();

        /** @var array<int, array<string, mixed>> $keys */
        $keys = $this->cache->remember($this->cacheKey($url), $ttl, function () use ($url): array {
            try {
                $timeout = $this->timeoutSeconds();

                $this->outboundUrls->assertTrustedForRequest($url, 'jwks_uri');

                $response = $this->http
                  ->timeout($timeout)
                  ->withOptions(['allow_redirects' => false])
                  ->acceptJson()
                  ->get($url);

                if (!$response->successful()) {
                    throw OidcJwksFetchFailed::forUrl($url);
                }

                $json = $response->json();

                if (!is_array($json)) {
                    throw OidcJwksFetchFailed::forUrl($url);
                }

                /** @var array<string, mixed> $payload */
                $payload = $json;

                $keys = $payload['keys'] ?? null;

                if (!is_array($keys)) {
                    throw OidcJwksFetchFailed::forUrl($url);
                }

                $out = [];

                foreach ($keys as $k) {
                    if (is_array($k)) {
                        /** @var array<string, mixed> $k */
                        $out[] = $k;
                    }
                }

                return $out;
            } catch (Throwable $e) {
                throw OidcJwksFetchFailed::forUrl($url, $e);
            }
        });

        return $keys;
    }

    private function ttlSeconds(): int
    {
        $value = config('sso.oidc.id_token.jwks_cache_ttl_seconds', 3600);

        return is_int($value) && $value > 0 ? $value : 3600;
    }

    private function cacheKey(string $url): string
    {
        return 'sso:oidc:jwks:' . sha1($url);
    }

    private function timeoutSeconds(): int
    {
        $value = config('sso.oidc.id_token.jwks_http_timeout_seconds', 10);

        return is_int($value) && $value > 0 ? $value : 10;
    }
}
