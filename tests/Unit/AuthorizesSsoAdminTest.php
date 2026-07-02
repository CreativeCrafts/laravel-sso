<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Http\Requests\Admin\TenantStoreRequest;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

it('allows admin requests when the gate is missing but allow_missing_gate is enabled', function (): void {
    Config::set('sso.ui.gate', 'missingManageSsoGate');
    Config::set('sso.ui.allow_missing_gate', true);

    $request = TenantStoreRequest::create('/admin/sso/tenants', 'POST', ['name' => 'Acme']);
    $request->setContainer(app());

    expect($request->authorize())->toBeTrue();
});

it('denies admin requests when the gate is missing and allow_missing_gate is disabled', function (): void {
    Config::set('sso.ui.gate', 'anotherMissingGate');
    Config::set('sso.ui.allow_missing_gate', false);

    $request = TenantStoreRequest::create('/admin/sso/tenants', 'POST', ['name' => 'Acme']);
    $request->setContainer(app());

    expect($request->authorize())->toBeFalse();
});

it('uses gate allows result when the gate is registered', function (): void {
    Config::set('sso.ui.gate', 'manageSso');
    Gate::define('manageSso', static fn (AuthenticatableUser $user): bool => $user->id === 1);

    $user = new AuthenticatableUser();
    $user->id = 1;

    $this->actingAs($user);

    $request = TenantStoreRequest::create('/admin/sso/tenants', 'POST', ['name' => 'Acme']);
    $request->setContainer(app());

    expect($request->authorize())->toBeTrue();
});
