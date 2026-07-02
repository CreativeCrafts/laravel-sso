<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\HostnameResolver;
use CreativeCrafts\LaravelSso\Contracts\Core\IdpOutboundUrlPolicy;
use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class DefaultIdpOutboundUrlPolicy implements IdpOutboundUrlPolicy
{
    /**
     * DNS resolution is evaluated at request time. Hostnames may resolve differently
     * later due to TTL changes or rebinding; callers disable HTTP redirects to reduce
     * follow-up request risk after this check completes.
     */
    public function __construct(
        private UrlTrustPolicy $urls,
        private HostnameResolver $resolver,
        private Config $config,
    ) {
    }

    public function assertTrustedForRequest(string $url, string $field): void
    {
        $this->urls->assertTrusted($url, $field);

        if ($this->allowPrivateUrls()) {
            return;
        }

        $host = $this->host($url);
        if ($host === null) {
            throw UnsafeIdpUrl::forField($field, 'URL host is missing.');
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw UnsafeIdpUrl::forField($field, 'URL host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (!$this->isAllowedIpAddress($address)) {
                throw UnsafeIdpUrl::forField($field, 'URL host does not resolve to a globally reachable address.');
            }
        }
    }

    private function host(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (!is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (!is_string($host) || $host === '') {
            return null;
        }

        return strtolower(trim($host, '[]'));
    }

    private function allowPrivateUrls(): bool
    {
        return (bool) $this->config->get('sso.security.allow_private_idp_urls', false);
    }

    private function isAllowedIpAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE,
        ) !== false;
    }
}
