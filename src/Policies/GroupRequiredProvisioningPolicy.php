<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;

/**
 * Example policy: allow provisioning only when the IdP claims include at least
 * one configured group. Bind this implementation in the host app when claim-aware
 * provisioning is required; the package default policy ignores claims by design.
 */
final readonly class GroupRequiredProvisioningPolicy implements ProvisioningPolicy
{
    /**
     * @param array<string, mixed> $claims
     */
    public function allows(
        Tenant $tenant,
        Connection $connection,
        IdentityProvider $identityProvider,
        array $claims,
    ): bool {
        $requiredGroups = $this->requiredGroups($connection);

        if ($requiredGroups === []) {
            return false;
        }

        $claimGroups = $this->claimGroups($claims);

        foreach ($requiredGroups as $requiredGroup) {
            if (in_array($requiredGroup, $claimGroups, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function requiredGroups(Connection $connection): array
    {
        /** @var array<string, mixed>|null $rawSettings */
        $rawSettings = $connection->settings;

        /** @var array<string, mixed> $settings */
        $settings = is_array($rawSettings) ? $rawSettings : [];

        $groups = $settings['required_provision_groups'] ?? null;

        if (!is_array($groups)) {
            return [];
        }

        $out = [];

        foreach ($groups as $group) {
            if (is_string($group) && $group !== '') {
                $out[] = $group;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $claims
     * @return list<string>
     */
    private function claimGroups(array $claims): array
    {
        $groups = $claims['groups'] ?? [];

        if (!is_array($groups)) {
            return [];
        }

        $out = [];

        foreach ($groups as $group) {
            if (is_string($group) && $group !== '') {
                $out[] = $group;
            }
        }

        return array_values(array_unique($out));
    }
}
