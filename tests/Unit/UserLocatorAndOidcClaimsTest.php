<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\DefaultUserLocator;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcClaimsNormalizer;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

function ensureLocatorUsersTable(): void
{
    if (!Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    Config::set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    Config::set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);
}

it('finds users by email for configured guards', function (): void {
    ensureLocatorUsersTable();
    User::query()->create(['email' => 'found@example.test', 'name' => 'Found']);

    $located = (new DefaultUserLocator(app('config')))->findByEmail('web', 'found@example.test');

    expect($located)->not->toBeNull()
        ->and($located?->getAuthIdentifier())->not->toBeNull();
});

it('returns null when the guard model cannot be resolved', function (): void {
    expect((new DefaultUserLocator(app('config')))->findByEmail('missing', 'user@example.test'))->toBeNull();
});

it('normalizes oidc claims including derived display names and groups', function (): void {
    $claims = (new DefaultOidcClaimsNormalizer())->normalize([
        'sub' => 'subject-1',
        'given_name' => 'Ada',
        'family_name' => 'Lovelace',
        'email_verified' => true,
        'groups' => ['staff', 123, ''],
    ]);

    expect($claims->subject)->toBe('subject-1')
        ->and($claims->displayName)->toBe('Ada Lovelace')
        ->and($claims->emailVerified)->toBeTrue()
        ->and($claims->groups)->toBe(['staff']);
});

it('throws when oidc claims are missing a subject', function (): void {
    (new DefaultOidcClaimsNormalizer())->normalize(['email' => 'user@example.test']);
})->throws(OidcIdTokenValidationFailed::class);
