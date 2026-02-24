<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\GuardSelector;
use CreativeCrafts\LaravelSso\Exceptions\GuardSelectionFailed;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class DefaultGuardSelector implements GuardSelector
{
    public function __construct(
        private ConfigRepository $config,
    ) {
    }

    public function selectGuard(Tenant $tenant, Connection $connection): string
    {
        $guard = $this->pickGuardName($connection);

        $allowed = $this->allowedGuards();
        if ($allowed !== [] && !in_array($guard, $allowed, true)) {
            throw GuardSelectionFailed::notAllowed($guard);
        }

        $guardConfig = $this->config->get('auth.guards.' . $guard);

        if (!is_array($guardConfig) || $guardConfig === []) {
            throw GuardSelectionFailed::notConfigured($guard);
        }

        return $guard;
    }

    private function pickGuardName(Connection $connection): string
    {
        if (is_string($connection->guard) && $connection->guard !== '') {
            return $connection->guard;
        }

        $default = $this->config->get('sso.guards.default');
        if (is_string($default) && $default !== '') {
            return $default;
        }

        $fallback = $this->config->get('auth.defaults.guard', 'web');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'web';
    }

    /**
     * @return array<int, string>
     */
    private function allowedGuards(): array
    {
        $allowed = $this->config->get('sso.guards.allowed');

        if (!is_array($allowed)) {
            return [];
        }

        $out = [];

        foreach ($allowed as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }
}
