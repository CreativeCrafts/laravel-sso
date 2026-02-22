<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Http\Controllers\SamlAcsController;
use CreativeCrafts\LaravelSso\Http\Controllers\SamlSpMetadataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
  'prefix' => config('sso.routes.prefix', 'sso'),
  'middleware' => config('sso.routes.middleware', ['web']),
  'as' => 'sso.',
], static function (): void {
    Route::get('{tenant}/{idp}/redirect', static function (Request $request, string $tenant, string $idp) {
        abort(501, 'SSO redirect endpoint not implemented yet.');
    })->name('redirect');

    Route::get('{tenant}/{idp}/callback', static function (Request $request, string $tenant, string $idp) {
        abort(501, 'OIDC callback endpoint not implemented yet.');
    })->name('oidc.callback');

    Route::post('{tenant}/{idp}/acs', static function (Request $request, string $tenant, string $idp) {
        return app(SamlAcsController::class)($request, $tenant, $idp);
    })->name('saml.acs');

    Route::get('{tenant}/{idp}/metadata', static function (Request $request, string $tenant, string $idp) {
        return app(SamlSpMetadataController::class)($request, $tenant, $idp);
    })->name('saml.metadata');
});
