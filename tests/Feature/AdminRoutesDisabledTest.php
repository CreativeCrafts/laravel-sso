<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Support\UiDisabledTestCase;
use Illuminate\Support\Facades\Route;

uses(UiDisabledTestCase::class);

it('does not register admin ui routes when ui is disabled', function () {
    expect(config('sso.ui.enabled', false))
      ->toBeFalse()
      ->and(Route::has('sso.ui.home'))->toBeFalse();
});
