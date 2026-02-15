<?php

namespace CreativeCrafts\LaravelSso;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use CreativeCrafts\LaravelSso\Commands\LaravelSsoCommand;

class LaravelSsoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-sso')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_sso_table')
            ->hasCommand(LaravelSsoCommand::class);
    }
}
