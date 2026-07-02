<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

it('publishes config migrations and ui assets', function (): void {
    $configPath = config_path('sso.php');

    if (File::exists($configPath)) {
        File::delete($configPath);
    }

    $this->artisan('sso:install --force')
        ->assertExitCode(0);

    expect(File::exists($configPath))->toBeTrue();
    expect(File::exists(database_path('migrations')))->toBeTrue();

    $migrationFiles = collect(File::glob(database_path('migrations/*sso*.php')));

    expect($migrationFiles->count())->toBeGreaterThan(0);
});

it('runs migrations when requested', function (): void {
    $this->artisan('sso:install --force --run-migrations')
        ->assertExitCode(0);
});
