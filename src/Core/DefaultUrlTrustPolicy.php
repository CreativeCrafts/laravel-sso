<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\UrlTrustPolicy;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class DefaultUrlTrustPolicy implements UrlTrustPolicy
{
    public function __construct(private Config $config)
    {
    }

    public function assertTrusted(string $url, string $field): void
    {
        $reason = $this->unsafeReason($url);

        if ($reason !== null) {
            throw UnsafeIdpUrl::forField($field, $reason);
        }
    }

    public function isTrusted(string $url): bool
    {
        return $this->unsafeReason($url) === null;
    }

    private function unsafeReason(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return 'URL is empty.';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return 'URL contains control characters.';
        }

        if (str_contains($url, '\\')) {
            return 'URL contains backslashes.';
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return 'URL is malformed.';
        }

        $scheme = $parts['scheme'] ?? null;
        $scheme = is_string($scheme) ? strtolower($scheme) : '';

        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'URL scheme must be http or https.';
        }

        if ($scheme !== 'https' && !$this->allowInsecureUrls()) {
            return 'URL must use https.';
        }

        if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
            return 'URL must not contain embedded credentials.';
        }

        $host = $parts['host'] ?? null;
        $host = is_string($host) ? strtolower(trim($host, '[]')) : '';

        if ($host === '') {
            return 'URL host is missing.';
        }

        if ($this->isAlwaysUnsafeHostname($host)) {
            return 'URL host is local or reserved.';
        }

        if ($this->isIpAddress($host) && !$this->isAllowedIpAddress($host)) {
            return 'URL host resolves to a private or reserved address.';
        }

        return null;
    }

    private function allowInsecureUrls(): bool
    {
        return (bool) $this->config->get('sso.security.allow_insecure_idp_urls', false);
    }

    private function allowPrivateUrls(): bool
    {
        return (bool) $this->config->get('sso.security.allow_private_idp_urls', false);
    }

    private function isAlwaysUnsafeHostname(string $host): bool
    {
        if ($this->allowPrivateUrls()) {
            return false;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return true;
        }

        if (str_ends_with($host, '.localhost')) {
            return true;
        }

        if ($host === 'metadata.google.internal') {
            return true;
        }

        return false;
    }

    private function isIpAddress(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    private function isAllowedIpAddress(string $host): bool
    {
        if ($this->allowPrivateUrls()) {
            return true;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
