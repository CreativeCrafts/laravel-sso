<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso;

use CreativeCrafts\LaravelSso\Contracts\Core\GuardSelector;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Core\DefaultGuardSelector;
use CreativeCrafts\LaravelSso\Core\Tenancy\CompositeTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\DefaultTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\RouteParamTenantResolver;
use CreativeCrafts\LaravelSso\Policies\AllowIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\AllowProvisioningPolicy;
use CreativeCrafts\LaravelSso\Repositories\EloquentConnectionRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentIdentityProviderRepository;
use Illuminate\Contracts\Container\Container;
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

    public function registeringPackage(): void
    {
        $this->app->singleton(IdentityProviderRepository::class, EloquentIdentityProviderRepository::class);
        $this->app->singleton(ConnectionRepository::class, EloquentConnectionRepository::class);

        $this->app->singleton(GuardSelector::class, DefaultGuardSelector::class);
        $this->app->singleton(ProvisioningPolicy::class, AllowProvisioningPolicy::class);
        $this->app->singleton(IdentityLinkPolicy::class, AllowIdentityLinkPolicy::class);

        $this->app->singleton(RouteParamTenantResolver::class, function (Container $app): RouteParamTenantResolver {
            $value = config('sso.tenancy.route_param', 'tenant');

            return new RouteParamTenantResolver(
                routeParam: is_string($value) && $value !== '' ? $value : 'tenant',
            );
        });

        $this->app->singleton(DefaultTenantResolver::class, function (Container $app): DefaultTenantResolver {
            $value = config('sso.tenancy.default_tenant_ulid');

            return new DefaultTenantResolver(
                defaultTenantUlid: is_string($value) && $value !== '' ? $value : null,
            );
        });

        $this->app->singleton(TenantResolver::class, function (Container $app): TenantResolver {
            /** @var array<int, TenantResolver> $resolvers */
            $resolvers = [
              $app->make(RouteParamTenantResolver::class),
              $app->make(DefaultTenantResolver::class),
            ];

            return new CompositeTenantResolver($resolvers);
        });
    }

    public function packageBooted(): void
    {
        if (config('sso.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/sso.php');
        }

        if (config('sso.ui.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');
        }
    }
}
