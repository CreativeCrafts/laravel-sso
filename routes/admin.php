<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
  'prefix' => config('sso.ui.prefix', 'admin/sso'),
  'middleware' => array_values(
      array_unique(
          array_merge(
              config('sso.ui.middleware', ['web', 'auth']),
              ['can:' . config('sso.ui.gate', 'manageSso')],
          ),
      ),
  ),
  'as' => 'sso.ui.',
], static function (): void {
    Route::get('/', static function (Request $request) {
        // Phase 5+: this becomes Inertia::render(...) pages.
        return response()->json([
          'message' => 'SSO Admin UI scaffold',
        ]);
    })->name('home');
});
