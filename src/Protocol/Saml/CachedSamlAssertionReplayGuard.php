<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionReplayGuard;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionReplayDetected;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class CachedSamlAssertionReplayGuard implements SamlAssertionReplayGuard
{
    public function __construct(private readonly CacheRepository $cache)
    {
    }

    public function assertNotReplayed(string $assertionId, int $ttlSeconds): void
    {
        if ($assertionId === '' || $ttlSeconds <= 0) {
            return;
        }

        if (!$this->cache->add($this->cacheKey($assertionId), true, $ttlSeconds)) {
            throw SamlAssertionReplayDetected::make();
        }
    }

    public function markConsumed(string $assertionId, int $ttlSeconds): void
    {
        if ($assertionId === '' || $ttlSeconds <= 0) {
            return;
        }

        $this->cache->put($this->cacheKey($assertionId), true, $ttlSeconds);
    }

    private function cacheKey(string $assertionId): string
    {
        return 'sso:saml:assertion:' . sha1($assertionId);
    }
}
