<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class DefaultIdentityLinkPolicy implements IdentityLinkPolicy
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function allows(
        Tenant $tenant,
        Connection $connection,
        IdentityProvider $identityProvider,
        Authenticatable $user,
        array $claims,
    ): bool {
        $override = $this->connectionOverride($connection);

        return $override ?? (bool) $this->config->get('sso.linking.enabled_by_default', false);
    }

    private function connectionOverride(Connection $connection): ?bool
    {
        $key = $this->config->get('sso.linking.connection_setting_key', 'allow_identity_linking');

        if (!is_string($key) || $key === '') {
            return null;
        }

        /** @var array<string, mixed> $settings */
        $settings = $connection->settings;

        if (!array_key_exists($key, $settings)) {
            return null;
        }

        return $this->normalizeBoolean($settings[$key]);
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : null;
    }
}
