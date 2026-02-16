<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Route;

uses(TestCase::class);

it('registers core sso routes when enabled', function () {
    expect(Route::has('sso.redirect'))
      ->toBeTrue()
      ->and(Route::has('sso.oidc.callback'))->toBeTrue()
      ->and(Route::has('sso.saml.acs'))->toBeTrue()
      ->and(Route::has('sso.saml.metadata'))->toBeTrue();
});