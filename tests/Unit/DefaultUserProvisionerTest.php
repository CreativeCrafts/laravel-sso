<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\DefaultUserProvisioner;
use CreativeCrafts\LaravelSso\Exceptions\UserEmailAlreadyExists;
use CreativeCrafts\LaravelSso\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

function ensureProvisionerUsersTable(): void
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

it('provisions a new authenticatable user for a configured guard', function (): void {
    ensureProvisionerUsersTable();

    $user = (new DefaultUserProvisioner(app('config')))->provision(
        guard: 'web',
        email: 'new-user@example.test',
        displayName: 'New User',
        claims: ['email' => 'new-user@example.test'],
    );

    expect($user->getAuthIdentifier())->not->toBeNull()
        ->and(User::query()->where('email', 'new-user@example.test')->exists())->toBeTrue();
});

it('throws when the guard cannot be resolved to a model', function (): void {
    Config::set('auth.guards.unknown', ['driver' => 'session', 'provider' => 'missing']);

    (new DefaultUserProvisioner(app('config')))->provision('unknown', 'user@example.test', null, []);
})->throws(RuntimeException::class);

it('maps unique constraint violations to user email already exists', function (): void {
    ensureProvisionerUsersTable();
    User::query()->create(['email' => 'dup@example.test', 'name' => 'Dup']);

    (new DefaultUserProvisioner(app('config')))->provision('web', 'dup@example.test', null, []);
})->throws(UserEmailAlreadyExists::class);

it('detects unique constraint violations from sql error messages', function (): void {
    $provisioner = new DefaultUserProvisioner(app('config'));
    $method = new ReflectionMethod($provisioner, 'isUniqueConstraintViolation');
    $method->setAccessible(true);

    $duplicate = new QueryException('sqlite', 'insert', [], new RuntimeException('duplicate key value violates unique constraint'));
    $other = new QueryException('sqlite', 'insert', [], new RuntimeException('syntax error'));

    expect($method->invoke($provisioner, $duplicate))->toBeTrue()
        ->and($method->invoke($provisioner, $other))->toBeFalse();
});
