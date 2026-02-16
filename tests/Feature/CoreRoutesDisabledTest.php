<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Support\RoutesDisabledTestCase;
use Illuminate\Support\Facades\Route;

uses(RoutesDisabledTestCase::class);

it('does not register core sso routes when disabled', function () {
    expect(Route::has('sso.redirect'))
      ->toBeFalse()
      ->and(Route::has('sso.oidc.callback'))->toBeFalse()
      ->and(Route::has('sso.saml.acs'))->toBeFalse()
      ->and(Route::has('sso.saml.metadata'))->toBeFalse();
});
