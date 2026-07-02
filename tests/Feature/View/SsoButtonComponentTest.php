<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use CreativeCrafts\LaravelSso\View\Components\SsoButton;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;

uses(TestCase::class);
uses(SsoTestHelpers::class);

it('builds redirect url with optional redirect to parameter', function (): void {
    Config::set('sso.routes.enabled', true);

    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    $url = (new SsoButton(
        tenant: $tenant->ulid,
        connection: $connection->ulid,
        redirectTo: '/dashboard',
    ))->url();

    expect($url)
        ->toContain('/sso/')
        ->toContain('redirect_to=' . rawurlencode('/dashboard'));
});

it('returns empty url when routes are disabled', function (): void {
    Config::set('sso.routes.enabled', false);

    expect((new SsoButton('tenant', 'connection'))->url())->toBe('');
});

it('registers the sso button blade view', function (): void {
    expect(View::exists('laravel-sso::components.sso-button'))->toBeTrue();
});

it('renders anchor html via the blade component tag', function (): void {
    Config::set('sso.routes.enabled', true);

    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    $view = $this->blade(
        '<x-sso-button :tenant="$tenant" :connection="$connection" label="Sign in with SSO" redirect-to="/home" />',
        [
            'tenant' => $tenant->ulid,
            'connection' => $connection->ulid,
        ],
    );

    $view->assertSee('Sign in with SSO', false);
    $view->assertSee('/sso/', false);
});
