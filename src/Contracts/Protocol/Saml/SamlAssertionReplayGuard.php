<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

interface SamlAssertionReplayGuard
{
    public function assertNotReplayed(string $assertionId, int $ttlSeconds): void;

    public function markConsumed(string $assertionId, int $ttlSeconds): void;
}
