<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Http\Controllers\OidcCallbackController;
use CreativeCrafts\LaravelSso\Http\Controllers\SamlAcsController;
use CreativeCrafts\LaravelSso\Http\Controllers\SamlSpMetadataController;
use CreativeCrafts\LaravelSso\Http\Controllers\SsoRedirectController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('sso.routes.prefix', 'sso'),
    'middleware' => config('sso.routes.middleware', ['web']),
    'as' => 'sso.',
], static function (): void {
    Route::get('{tenant}/{connection}/redirect', SsoRedirectController::class)
      ->middleware('throttle:sso.redirect')
      ->name('redirect');

    Route::get('{tenant}/{connection}/callback', OidcCallbackController::class)
      ->middleware('throttle:sso.callback')
      ->name('oidc.callback');

    Route::post('{tenant}/{connection}/acs', SamlAcsController::class)
      ->middleware('throttle:sso.acs')
      ->name('saml.acs');

    Route::get('{tenant}/{connection}/metadata', SamlSpMetadataController::class)
      ->middleware('throttle:sso.metadata')
      ->name('saml.metadata');
});
