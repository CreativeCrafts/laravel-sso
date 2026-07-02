<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Tenancy\RouteParamTenantResolver;
use CreativeCrafts\LaravelSso\Events\Dto\CallbackEventSummary;
use CreativeCrafts\LaravelSso\Repositories\EloquentTenantRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

it('redacts callback event summary fields', function (): void {
    $summary = CallbackEventSummary::fromResult('oidc', new DriverCallbackResult(
        authenticated: true,
        canonicalClaims: new Claims('sub', 'user@example.test', 'Display Name', true, [], []),
        subject: 'sub-123',
        email: 'user@example.test',
        displayName: 'Display Name',
        claims: [],
        context: [],
    ));

    expect($summary->email)->toBe('u***@example.test')
        ->and($summary->displayName)->toBe('D***');

    $invalid = CallbackEventSummary::fromResult('oidc', new DriverCallbackResult(
        authenticated: false,
        canonicalClaims: new Claims('sub', null, null, null, [], []),
        subject: null,
        email: 'not-an-email',
        displayName: '',
        error: 'failed',
        claims: [],
        context: [],
    ));

    expect($invalid->email)->toBe('***')
        ->and($invalid->displayName)->toBe('');
});

it('returns null from route param resolver when route data is missing', function (): void {
    $resolver = new RouteParamTenantResolver('tenant', new EloquentTenantRepository());

    expect($resolver->resolve(Request::create('/sso/start', 'GET')))->toBeNull();
});

it('reports doctor issues for disabled sso and invalid driver config', function (): void {
    Config::set('sso.enabled', false);
    Config::set('sso.drivers', []);
    Config::set('sso.oidc.callback.http_timeout_seconds', 0);

    $this->artisan('sso:doctor --strict')
        ->assertExitCode(1);
});
