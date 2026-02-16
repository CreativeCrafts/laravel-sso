<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

function setEnvFlag(string $key, bool $value): void
{
    $stringValue = $value ? 'true' : 'false';

    putenv($key . '=' . $stringValue);
    $_ENV[$key] = $stringValue;
    $_SERVER[$key] = $stringValue;
}

it('registers core sso routes when enabled', function () {
    setEnvFlag('SSO_ROUTES_ENABLED', true);

    $this->refreshApplication();

    expect(Route::has('sso.redirect'))
      ->toBeTrue()
      ->and(Route::has('sso.oidc.callback'))->toBeTrue()
      ->and(Route::has('sso.saml.acs'))->toBeTrue()
      ->and(Route::has('sso.saml.metadata'))->toBeTrue();
});

it('does not register core sso routes when disabled', function () {
    setEnvFlag('SSO_ROUTES_ENABLED', false);

    $this->refreshApplication();

    expect(Route::has('sso.redirect'))
      ->toBeFalse()
      ->and(Route::has('sso.oidc.callback'))->toBeFalse()
      ->and(Route::has('sso.saml.acs'))->toBeFalse()
      ->and(Route::has('sso.saml.metadata'))->toBeFalse();
});

it('registers admin ui routes only when ui is enabled', function () {
    setEnvFlag('SSO_ROUTES_ENABLED', true);
    setEnvFlag('SSO_UI_ENABLED', false);

    $this->refreshApplication();

    expect(Route::has('sso.ui.home'))->toBeFalse();

    setEnvFlag('SSO_ROUTES_ENABLED', true);
    setEnvFlag('SSO_UI_ENABLED', true);

    $this->refreshApplication();

    expect(Route::has('sso.ui.home'))->toBeTrue();
});