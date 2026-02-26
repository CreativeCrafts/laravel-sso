<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Support\UiEnabledTestCase;
use Illuminate\Support\Facades\Route;

uses(UiEnabledTestCase::class);

it('registers admin ui routes when ui is enabled', function () {
    expect(Route::has('sso.ui.home'))->toBeTrue();
});

it('protects admin ui routes with the configured can gate', function () {
    $route = Route::getRoutes()->getByName('sso.ui.home');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('can:manageSso');
});
