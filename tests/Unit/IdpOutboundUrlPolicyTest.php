<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Core\HostnameResolver;
use CreativeCrafts\LaravelSso\Contracts\Core\IdpOutboundUrlPolicy;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;

it('allows public hostnames that resolve to public addresses', function (): void {
    bindFakeResolver([
        'idp.example.com' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    $policy->assertTrustedForRequest('https://idp.example.com/.well-known/openid-configuration', 'discovery_url');

    expect(true)->toBeTrue();
});

it('rejects hostnames that resolve to loopback addresses', function (): void {
    bindFakeResolver([
        'idp.example.com' => ['127.0.0.1'],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    expect(fn () => $policy->assertTrustedForRequest('https://idp.example.com/.well-known/openid-configuration', 'discovery_url'))
        ->toThrow(UnsafeIdpUrl::class);
});

it('rejects hostnames that resolve to private RFC1918 addresses', function (): void {
    bindFakeResolver([
        'idp.example.com' => ['10.0.0.10'],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    expect(fn () => $policy->assertTrustedForRequest('https://idp.example.com/token', 'token_endpoint'))
        ->toThrow(UnsafeIdpUrl::class);
});

it('rejects hostnames that resolve to IPv6 link local addresses', function (): void {
    bindFakeResolver([
        'idp.example.com' => ['fe80::1'],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    expect(fn () => $policy->assertTrustedForRequest('https://idp.example.com/jwks', 'jwks_uri'))
        ->toThrow(UnsafeIdpUrl::class);
});

it('rejects mixed public and private DNS answers', function (): void {
    bindFakeResolver([
        'idp.example.com' => ['93.184.216.34', '192.168.10.10'],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    expect(fn () => $policy->assertTrustedForRequest('https://idp.example.com/userinfo', 'userinfo_endpoint'))
        ->toThrow(UnsafeIdpUrl::class);
});

it('rejects unresolved hostnames by default', function (): void {
    bindFakeResolver([
        'idp.example.com' => [],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    expect(fn () => $policy->assertTrustedForRequest('https://idp.example.com/userinfo', 'userinfo_endpoint'))
        ->toThrow(UnsafeIdpUrl::class);
});

it('allows private resolved addresses only when private IdP URLs are explicitly enabled', function (): void {
    config()->set('sso.security.allow_private_idp_urls', true);

    bindFakeResolver([
        'idp.example.com' => ['10.0.0.10'],
    ]);

    /** @var IdpOutboundUrlPolicy $policy */
    $policy = app(IdpOutboundUrlPolicy::class);

    $policy->assertTrustedForRequest('https://idp.example.com/token', 'token_endpoint');

    expect(true)->toBeTrue();
});

/**
 * @param array<string, array<int, string>> $records
 */
function bindFakeResolver(array $records): void
{
    app()->bind(HostnameResolver::class, static fn (): HostnameResolver => new class ($records) implements HostnameResolver {
        /** @param array<string, array<int, string>> $records */
        public function __construct(private readonly array $records)
        {
        }

        public function resolve(string $hostname): array
        {
            return $this->records[strtolower(trim($hostname, '[]'))] ?? [];
        }
    });

    app()->forgetInstance(IdpOutboundUrlPolicy::class);
}
