<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Protocol\Saml\SpMetadataGenerator;
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
        abort(501, 'SAML ACS endpoint not implemented yet.');
    })->name('saml.acs');

    Route::get('{tenant}/{idp}/metadata', static function (string $tenant, string $idp) {
        $xml = app(SpMetadataGenerator::class)->generate($tenant, $idp);

        return response($xml, 200)
          ->header('Content-Type', 'application/samlmetadata+xml; charset=UTF-8');
    })->name('saml.metadata');
});
