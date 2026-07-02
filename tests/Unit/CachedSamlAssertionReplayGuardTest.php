<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionReplayDetected;
use CreativeCrafts\LaravelSso\Protocol\Saml\CachedSamlAssertionReplayGuard;
use Illuminate\Support\Facades\Cache;

it('rejects duplicate assertion ids within the ttl window', function (): void {
    Cache::flush();

    $guard = new CachedSamlAssertionReplayGuard(Cache::store());

    $guard->assertNotReplayed('_assertion-1', 60);

    expect(fn (): mixed => $guard->assertNotReplayed('_assertion-1', 60))
        ->toThrow(SamlAssertionReplayDetected::class);
});

it('ignores empty assertion ids and non positive ttl', function (): void {
    $guard = new CachedSamlAssertionReplayGuard(Cache::store());

    $guard->assertNotReplayed('', 60);
    $guard->assertNotReplayed('_assertion-2', 0);
    $guard->markConsumed('', 60);
    $guard->markConsumed('_assertion-3', -1);

    expect(true)->toBeTrue();
});
