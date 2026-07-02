<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Policies;

use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Example policy: allow linking only when the IdP claims include at least one
 * configured group. Bind this implementation in the host app when claim-aware
 * linking is required; the package default policy ignores claims by design.
 */
final readonly class GroupRequiredIdentityLinkPolicy implements IdentityLinkPolicy
{
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

        $groups = $settings['required_link_groups'] ?? null;

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
