<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
  'prefix' => config('sso.routes.prefix', 'sso'),
  'middleware' => config('sso.routes.middleware', ['web']),
  'as' => 'sso.',
], static function (): void {
    Route::get('{tenant}/{idp}/redirect', static function (Request $request, string $tenant, string $idp) {
        // Phase 1+: BeginLogin use-case will generate the IdP redirect.
        // Keeping this explicit stub prevents missing-route failures during scaffolding.
        abort(501, 'SSO redirect endpoint not implemented yet.');
    })->name('redirect');

    Route::get('{tenant}/{idp}/callback', static function (Request $request, string $tenant, string $idp) {
        // Phase 2+: OIDC HandleCallback pipeline.
        abort(501, 'OIDC callback endpoint not implemented yet.');
    })->name('oidc.callback');

    Route::post('{tenant}/{idp}/acs', static function (Request $request, string $tenant, string $idp) {
        // Phase 3+: SAML ACS HandleCallback pipeline.
        abort(501, 'SAML ACS endpoint not implemented yet.');
    })->name('saml.acs');

    Route::get('{tenant}/{idp}/metadata', static function (string $tenant, string $idp) {
        // Phase 3+: Return SP metadata XML for SAML.
        abort(501, 'SAML metadata endpoint not implemented yet.');
    })->name('saml.metadata');
});
