<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests;

use CreativeCrafts\LaravelSso\Contracts\Core\HostnameResolver;
use CreativeCrafts\LaravelSso\Contracts\Core\IdpOutboundUrlPolicy;
use CreativeCrafts\LaravelSso\LaravelSsoServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(HostnameResolver::class, static fn (): HostnameResolver => new class () implements HostnameResolver {
            public function resolve(string $hostname): array
            {
                return ['93.184.216.34'];
            }
        });
        $this->app->forgetInstance(IdpOutboundUrlPolicy::class);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'CreativeCrafts\\LaravelSso\\Database\\Factories\\' . class_basename($modelName) . 'Factory',
        );

        $this->artisan('vendor:publish', [
          '--tag' => 'sso-migrations',
          '--force' => true,
        ])->run();

        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function getEnvironmentSetUp($app): void
    {
        // 32-byte key (base64-encoded) required for aes-256-* ciphers.
        $app['config']->set('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
        $app['config']->set('app.cipher', 'aes-256-cbc');
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

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
          'driver' => 'sqlite',
          'database' => ':memory:',
          'prefix' => '',
          'foreign_key_constraints' => true,
        ]);

        $app['config']->set('cache.default', 'array');
    }
}
