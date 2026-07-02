<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

uses(TestCase::class);

it('reports ok status when defaults valid', function (): void {
    $this->artisan('sso:doctor')
        ->assertExitCode(0);
});

it('exits non-zero in strict mode when admin ui enabled without gate', function (): void {
    Config::set('sso.ui.enabled', true);
    Config::set('sso.ui.allow_missing_gate', false);

    $this->artisan('sso:doctor --strict')
        ->assertExitCode(1);
});

it('outputs json with issues and warnings keys', function (): void {
    Config::set('sso.provisioning.enabled_by_default', true);

    $exitCode = Artisan::call('sso:doctor', ['--json' => true]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['status', 'ok', 'issues', 'warnings'])
        ->and($payload['status'])->toBe('warnings')
        ->and($payload['ok'])->toBeTrue()
        ->and($payload['issues'])->toBe([])
        ->and($payload['warnings'])->not->toBeEmpty();
});
