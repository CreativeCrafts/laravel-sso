<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\DefaultUrlTrustPolicy;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use Illuminate\Support\Facades\Config;

it('rejects malformed and insecure idp urls by default', function (string $url, string $field): void {
    (new DefaultUrlTrustPolicy(app('config')))->assertTrusted($url, $field);
})->throws(UnsafeIdpUrl::class)->with([
    'empty' => ['', 'issuer'],
    'control chars' => ["https://idp.test/\x07", 'issuer'],
    'backslash' => ['https://idp.test\\evil', 'issuer'],
    'bad scheme' => ['ftp://idp.test', 'issuer'],
    'http without allow flag' => ['http://idp.test', 'issuer'],
    'embedded credentials' => ['https://user:pass@idp.test', 'issuer'],
    'missing host' => ['https:///path', 'issuer'],
    'localhost' => ['https://localhost/callback', 'issuer'],
    'metadata host' => ['https://metadata.google.internal', 'issuer'],
    'private ip' => ['https://127.0.0.1/token', 'issuer'],
]);

it('allows insecure and private urls when explicitly enabled', function (): void {
    Config::set('sso.security.allow_insecure_idp_urls', true);
    Config::set('sso.security.allow_private_idp_urls', true);

    $policy = new DefaultUrlTrustPolicy(app('config'));

    expect($policy->isTrusted('http://127.0.0.1/token'))->toBeTrue()
        ->and($policy->isTrusted('https://localhost/token'))->toBeTrue();
});

it('rejects localhost subdomains unless private urls are allowed', function (): void {
    $policy = new DefaultUrlTrustPolicy(app('config'));

    expect($policy->isTrusted('https://tenant.localhost/token'))->toBeFalse();
});
