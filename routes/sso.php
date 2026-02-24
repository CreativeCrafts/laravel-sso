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
    Route::get('{tenant}/{idp}/redirect', SsoRedirectController::class)->name('redirect');

    Route::get('{tenant}/{idp}/callback', OidcCallbackController::class)->name('oidc.callback');

    Route::post('{tenant}/{idp}/acs', SamlAcsController::class)->name('saml.acs');

    Route::get('{tenant}/{idp}/metadata', SamlSpMetadataController::class)->name('saml.metadata');
});
