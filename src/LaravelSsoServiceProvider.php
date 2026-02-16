<?php

namespace CreativeCrafts\LaravelSso;

use CreativeCrafts\LaravelSso\Commands\LaravelSsoCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelSsoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
          ->name('laravel-sso')
          ->hasConfigFile('sso')
          ->hasMigration('create_sso_tables');
    }

    public function packageBooted(): void
    {
        if (config('sso.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/sso.php');
        }

        if (config('sso.ui.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');

            // $this->publishes([
            //     __DIR__ . '/../resources/ui' => resource_path('js/vendor/creativecrafts/laravel-sso'),
            // ], 'sso-ui');
        }
    }
}