<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Http\Controllers\Admin\ConnectionsController;
use CreativeCrafts\LaravelSso\Http\Controllers\Admin\IdentityProvidersController;
use CreativeCrafts\LaravelSso\Http\Controllers\Admin\SsoAdminHomeController;
use CreativeCrafts\LaravelSso\Http\Controllers\Admin\TenantsController;
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

    // Tenants
    Route::get('/tenants', [TenantsController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantsController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [TenantsController::class, 'show'])->name('tenants.show');
    Route::put('/tenants/{tenant}', [TenantsController::class, 'update'])->name('tenants.update');
    Route::patch('/tenants/{tenant}', [TenantsController::class, 'update']);
    Route::delete('/tenants/{tenant}', [TenantsController::class, 'destroy'])->name('tenants.destroy');

    // Identity Providers (tenant-scoped)
    Route::get('/tenants/{tenant}/idps', [IdentityProvidersController::class, 'index'])->name('idps.index');
    Route::post('/tenants/{tenant}/idps', [IdentityProvidersController::class, 'store'])->name('idps.store');
    Route::get('/tenants/{tenant}/idps/{idp}', [IdentityProvidersController::class, 'show'])->name('idps.show');
    Route::put('/tenants/{tenant}/idps/{idp}', [IdentityProvidersController::class, 'update'])->name('idps.update');
    Route::patch('/tenants/{tenant}/idps/{idp}', [IdentityProvidersController::class, 'update']);
    Route::delete('/tenants/{tenant}/idps/{idp}', [IdentityProvidersController::class, 'destroy'])->name('idps.destroy');

    // Connections (tenant-scoped)
    Route::get('/tenants/{tenant}/connections', [ConnectionsController::class, 'index'])->name('connections.index');
    Route::post('/tenants/{tenant}/connections', [ConnectionsController::class, 'store'])->name('connections.store');
    Route::get('/tenants/{tenant}/connections/{connection}', [ConnectionsController::class, 'show'])->name('connections.show');
    Route::put('/tenants/{tenant}/connections/{connection}', [ConnectionsController::class, 'update'])->name('connections.update');
    Route::patch('/tenants/{tenant}/connections/{connection}', [ConnectionsController::class, 'update']);
    Route::delete('/tenants/{tenant}/connections/{connection}', [ConnectionsController::class, 'destroy'])->name('connections.destroy');
});
