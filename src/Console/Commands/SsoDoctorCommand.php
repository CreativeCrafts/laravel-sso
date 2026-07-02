<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class SsoDoctorCommand extends Command
{
    protected $signature = 'sso:doctor {--json : Output machine-readable JSON} {--strict : Exit non-zero when blocking issues are found}';

    protected $description = 'Validate Laravel SSO configuration and surface common misconfigurations.';

    public function handle(): int
    {
        /** @var list<string> $issues */
        $issues = [];

        /** @var list<string> $warnings */
        $warnings = [];

        if (Config::get('app.key') === null || Config::get('app.key') === '') {
            $issues[] = 'APP_KEY is missing. Encrypted IdP config and PKCE verifiers require a stable application key.';
        }

        if (!Config::get('sso.enabled', true)) {
            $issues[] = 'SSO is disabled via sso.enabled.';
        }

        if (!Config::get('sso.routes.enabled', true)) {
            $warnings[] = 'Public SSO routes are disabled via sso.routes.enabled.';
        } elseif (!Route::has('sso.redirect')) {
            $issues[] = 'Public SSO routes are enabled but sso.redirect is not registered. Confirm the package service provider is loaded.';
        }

        $drivers = Config::get('sso.drivers');
        if (!is_array($drivers) || $drivers === []) {
            $issues[] = 'No SSO drivers configured in sso.drivers.';
        }

        if (Schema::hasTable('sso_tenants') === false) {
            $issues[] = 'Table sso_tenants is missing. Run php artisan sso:install --run-migrations or php artisan migrate.';
        }

        $oidcTimeout = Config::get('sso.oidc.callback.http_timeout_seconds');
        if (!is_int($oidcTimeout) || $oidcTimeout <= 0) {
            $issues[] = 'Invalid OIDC callback timeout (sso.oidc.callback.http_timeout_seconds).';
        }

        $entityId = Config::get('sso.saml.sp.entity_id');
        if (!is_string($entityId) || $entityId === '') {
            $warnings[] = 'SAML SP entity ID is not set; the package will default entityID to the metadata URL.';
        }

        if (Config::get('sso.ui.enabled', false)) {
            $gate = Config::get('sso.ui.gate', 'manageSso');
            $gate = is_string($gate) && $gate !== '' ? $gate : 'manageSso';

            if (!Gate::has($gate) && !Config::get('sso.ui.allow_missing_gate', false)) {
                $issues[] = sprintf(
                    'Admin API/UI is enabled but Gate::define("%s") is not registered and sso.ui.allow_missing_gate is false.',
                    $gate,
                );
            }
        }

        if (Config::get('sso.provisioning.enabled_by_default', false) || Config::get('sso.linking.enabled_by_default', false)) {
            $warnings[] = 'Provisioning or linking is enabled by default; confirm automatic account creation/linking is intended.';
        }

        if (Config::get('sso.security.allow_insecure_idp_urls', false) || Config::get('sso.security.allow_private_idp_urls', false)) {
            $warnings[] = 'Insecure or private IdP URL overrides are enabled; do not use these settings in production.';
        }

        if ($this->option('json')) {
            $payload = [
                'status' => $issues !== [] ? 'issues' : ($warnings !== [] ? 'warnings' : 'ok'),
                'ok' => $issues === [],
                'issues' => $issues,
                'warnings' => $warnings,
            ];
            $encoded = json_encode($payload, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '{}');
        } elseif ($issues === [] && $warnings === []) {
            $this->components->info('No issues detected.');
        } else {
            foreach ($issues as $issue) {
                $this->components->error($issue);
            }

            foreach ($warnings as $warning) {
                $this->components->warn($warning);
            }

            if ($issues === []) {
                $this->components->info('No blocking issues detected.');
            }
        }

        if ($this->option('strict')) {
            return $issues === [] ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
