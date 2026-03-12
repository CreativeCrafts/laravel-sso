<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso;

use CreativeCrafts\LaravelSso\Contracts\Core\AuthAttemptService;
use CreativeCrafts\LaravelSso\Contracts\Core\BeginLogin;
use CreativeCrafts\LaravelSso\Contracts\Core\DriverRegistry;
use CreativeCrafts\LaravelSso\Contracts\Core\GuardSelector;
use CreativeCrafts\LaravelSso\Contracts\Core\HandleCallback;
use CreativeCrafts\LaravelSso\Contracts\Core\ProvisionAndLink;
use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Contracts\Core\UserLocator;
use CreativeCrafts\LaravelSso\Contracts\Core\UserProvisioner;
use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcClaimsNormalizer;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcDiscovery;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcIdTokenValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionExtractor;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsMapper;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlMetadataParser;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Contracts\Repositories\AuthAttemptRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ConnectionRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\ExternalIdentityRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\IdentityProviderRepository;
use CreativeCrafts\LaravelSso\Contracts\Repositories\TenantRepository;
use CreativeCrafts\LaravelSso\Core\BeginLoginService;
use CreativeCrafts\LaravelSso\Core\ConfigDriverRegistry;
use CreativeCrafts\LaravelSso\Core\DbAuthAttemptService;
use CreativeCrafts\LaravelSso\Core\DefaultGuardSelector;
use CreativeCrafts\LaravelSso\Core\DefaultUserLocator;
use CreativeCrafts\LaravelSso\Core\DefaultUserProvisioner;
use CreativeCrafts\LaravelSso\Core\HandleCallbackService;
use CreativeCrafts\LaravelSso\Core\ProvisionAndLinkService;
use CreativeCrafts\LaravelSso\Core\Tenancy\CompositeTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\DefaultTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\HeaderTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\HostTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\RouteParamTenantResolver;
use CreativeCrafts\LaravelSso\Policies\DefaultIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\DefaultProvisioningPolicy;
use CreativeCrafts\LaravelSso\Protocol\Oidc\CachedOidcDiscovery;
use CreativeCrafts\LaravelSso\Protocol\Oidc\CachedOidcJwksFetcher;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcClaimsNormalizer;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcEndpointResolver;
use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcIdTokenValidator;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlAssertionExtractor;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlClaimsMapper;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlMetadataParser;
use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlSignatureValidator;
use CreativeCrafts\LaravelSso\Protocol\Saml\SpMetadataGenerator;
use CreativeCrafts\LaravelSso\Repositories\EloquentAuthAttemptRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentConnectionRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentExternalIdentityRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentIdentityProviderRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentTenantRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
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
        $this->app->singleton(TenantRepository::class, EloquentTenantRepository::class);
        $this->app->singleton(AuthAttemptRepository::class, EloquentAuthAttemptRepository::class);
        $this->app->singleton(ExternalIdentityRepository::class, EloquentExternalIdentityRepository::class);

        $this->app->singleton(GuardSelector::class, DefaultGuardSelector::class);
        $this->app->singleton(ProvisioningPolicy::class, DefaultProvisioningPolicy::class);
        $this->app->singleton(IdentityLinkPolicy::class, DefaultIdentityLinkPolicy::class);

        $this->app->singleton(SamlMetadataParser::class, DefaultSamlMetadataParser::class);
        $this->app->singleton(SpMetadataGenerator::class);
        $this->app->singleton(SamlSignatureValidator::class, DefaultSamlSignatureValidator::class);
        $this->app->singleton(SamlAssertionConditionsValidator::class, DefaultSamlAssertionConditionsValidator::class);
        $this->app->singleton(SamlAssertionExtractor::class, DefaultSamlAssertionExtractor::class);
        $this->app->singleton(SamlClaimsMapper::class, DefaultSamlClaimsMapper::class);
        $this->app->singleton(SamlClaimsNormalizer::class, DefaultSamlClaimsNormalizer::class);

        $this->app->singleton(RouteParamTenantResolver::class, function (): RouteParamTenantResolver {
            $value = config('sso.tenancy.route_param', 'tenant');

            return new RouteParamTenantResolver(
                routeParam: is_string($value) && $value !== '' ? $value : 'tenant',
            );
        });

        $this->app->singleton(DefaultTenantResolver::class, function (): DefaultTenantResolver {
            $value = config('sso.tenancy.default_tenant_ulid');

            return new DefaultTenantResolver(
                defaultTenantUlid: is_string($value) && $value !== '' ? $value : null,
            );
        });

        $this->app->singleton(HeaderTenantResolver::class, function (): HeaderTenantResolver {
            return new HeaderTenantResolver(
                enabled: (bool) config('sso.tenancy.header.enabled', false),
                headerName: Config::string('sso.tenancy.header.name', 'X-SSO-Tenant'),
            );
        });

        $this->app->singleton(HostTenantResolver::class, function (): HostTenantResolver {
            $mode = config('sso.tenancy.host.mode', 'host');
            $mode = is_string($mode) && $mode !== '' ? $mode : 'host';

            $baseDomain = config('sso.tenancy.host.base_domain');
            $baseDomain = is_string($baseDomain) && trim($baseDomain) !== '' ? trim($baseDomain) : null;

            return new HostTenantResolver(
                enabled: (bool) config('sso.tenancy.host.enabled', false),
                mode: $mode,
                baseDomain: $baseDomain,
            );
        });

        $this->app->singleton(TenantResolver::class, function (Container $app): TenantResolver {
            /** @var array<int, TenantResolver> $resolvers */
            $resolvers = [
              $app->make(RouteParamTenantResolver::class),
              $app->make(HeaderTenantResolver::class),
              $app->make(HostTenantResolver::class),
              $app->make(DefaultTenantResolver::class),
            ];

            return new CompositeTenantResolver(
                resolvers: $resolvers,
                throwIfMissing: (bool) config('sso.tenancy.throw_if_missing', true),
            );
        });
        $this->app->singleton(AuthAttemptService::class, DbAuthAttemptService::class);
        $this->app->singleton(DriverRegistry::class, ConfigDriverRegistry::class);
        $this->app->singleton(BeginLogin::class, BeginLoginService::class);
        $this->app->singleton(HandleCallback::class, HandleCallbackService::class);
        $this->app->singleton(UserLocator::class, DefaultUserLocator::class);
        $this->app->singleton(UserProvisioner::class, DefaultUserProvisioner::class);
        $this->app->singleton(ProvisionAndLink::class, ProvisionAndLinkService::class);
        $this->app->singleton(OidcDiscovery::class, CachedOidcDiscovery::class);
        $this->app->singleton(OidcEndpointResolver::class, DefaultOidcEndpointResolver::class);
        $this->app->singleton(OidcJwksFetcher::class, CachedOidcJwksFetcher::class);
        $this->app->singleton(OidcIdTokenValidator::class, DefaultOidcIdTokenValidator::class);
        $this->app->singleton(OidcClaimsNormalizer::class, DefaultOidcClaimsNormalizer::class);
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
              __DIR__ . '/../resources/ui' => base_path('resources/vendor/laravel-sso/ui'),
            ], 'sso-ui');
        }

        if (config('sso.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/sso.php');
        }

        if (config('sso.ui.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');
        }
    }
}
