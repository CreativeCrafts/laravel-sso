<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests;

use CreativeCrafts\LaravelSso\LaravelSsoServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'CreativeCrafts\\LaravelSso\\Database\\Factories\\' . class_basename($modelName) . 'Factory',
        );

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    /**
     * @param Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
          LaravelSsoServiceProvider::class,
        ];
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
          'driver' => 'sqlite',
          'database' => ':memory:',
          'prefix' => '',
          'foreign_key_constraints' => true,
        ]);
    }
}
