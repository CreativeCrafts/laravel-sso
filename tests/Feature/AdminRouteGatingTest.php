<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Tests\Support\UiEnabledTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(UiEnabledTestCase::class);

beforeEach(function () {
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);

    // Prevent `auth` middleware from throwing `Route [login] not defined.`
    Route::get('/login', static fn () => 'login')->name('login');
});

it('includes auth and can gate middleware on the ui home route', function () {
    $route = Route::getRoutes()->getByName('sso.ui.home');
    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)
      ->toContain('auth')
      ->and($middleware)->toContain('can:' . config('sso.ui.gate', 'manageSso'));
});

it('redirects guests via auth middleware', function () {
    Gate::define(config('sso.ui.gate', 'manageSso'), static fn (): bool => true);

    $response = $this->get('/' . ltrim(config('sso.ui.prefix', 'admin/sso'), '/'));

    $response->assertStatus(302);
});

it('returns 403 for authenticated but unauthorized users', function () {
    Gate::define(config('sso.ui.gate', 'manageSso'), static fn (): bool => false);

    $user = User::query()->create(['email' => 'user@example.test', 'name' => 'User']);

    $response = $this->actingAs($user, 'web')->get('/' . ltrim(config('sso.ui.prefix', 'admin/sso'), '/'));

    $response->assertForbidden();
});

it('returns 200 for authorized users', function () {
    Gate::define(config('sso.ui.gate', 'manageSso'), static fn (): bool => true);

    $user = User::query()->create(['email' => 'admin@example.test', 'name' => 'Admin']);

    $response = $this->actingAs($user, 'web')->get('/' . ltrim(config('sso.ui.prefix', 'admin/sso'), '/'));

    $response->assertOk();
    $response->assertJson([
      'message' => 'SSO Admin UI scaffold',
    ]);
});
