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
                throw UnsafeIdpUrl::forField($field, 'URL host resolved to a disallowed address.');
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
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->isAllowedIpv4Address($address);
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $this->isAllowedIpv6Address($address);
        }

        return false;
    }

    private function isAllowedIpv4Address(string $address): bool
    {
        $octets = array_map('intval', explode('.', $address));

        if (count($octets) !== 4) {
            return false;
        }

        [$first, $second, $third] = $octets;

        return match (true) {
            $first === 0 => false,
            $first === 10 => false,
            $first === 100 && $second >= 64 && $second <= 127 => false,
            $first === 127 => false,
            $first === 169 && $second === 254 => false,
            $first === 172 && $second >= 16 && $second <= 31 => false,
            $first === 192 && $second === 0 => false,
            $first === 192 && $second === 88 && $third === 99 => false,
            $first === 192 && $second === 168 => false,
            $first === 198 && ($second === 18 || $second === 19) => false,
            $first === 198 && $second === 51 && $third === 100 => false,
            $first === 203 && $second === 0 && $third === 113 => false,
            $first >= 224 => false,
            default => true,
        };
    }

    private function isAllowedIpv6Address(string $address): bool
    {
        $bytes = inet_pton($address);

        if (!is_string($bytes) || strlen($bytes) !== 16) {
            return false;
        }

        $firstWord = (ord($bytes[0]) << 8) + ord($bytes[1]);
        $secondWord = (ord($bytes[2]) << 8) + ord($bytes[3]);

        if (($firstWord & 0xe000) !== 0x2000) {
            return false;
        }

        return match (true) {
            $firstWord === 0x2001 && $secondWord < 0x0200 => false,
            $firstWord === 0x2001 && $secondWord === 0x0db8 => false,
            $firstWord === 0x2002 => false,
            $firstWord === 0x3fff && $secondWord < 0x1000 => false,
            default => true,
        };
    }
}
