<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Http\Controllers\Admin\SsoAdminHomeController;
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
    Route::get('/', SsoAdminHomeController::class)->name('home');
});
