<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

final class SsoDoctorCommand extends Command
{
    protected $signature = 'sso:doctor {--json : Output machine-readable JSON} {--strict : Exit non-zero when issues are found}';

    protected $description = 'Validate Laravel SSO configuration and surface common misconfigurations.';

    public function handle(): int
    {
        $issues = [];

        if (!Config::get('sso.enabled', true)) {
            $issues[] = 'SSO is disabled via sso.enabled.';
        }

        $drivers = Config::get('sso.drivers');
        if (!is_array($drivers) || $drivers === []) {
            $issues[] = 'No SSO drivers configured in sso.drivers.';
        }

        $oidcTimeout = Config::get('sso.oidc.callback.http_timeout_seconds');
        if (!is_int($oidcTimeout) || $oidcTimeout <= 0) {
            $issues[] = 'Invalid OIDC callback timeout (sso.oidc.callback.http_timeout_seconds).';
        }

        $samlCerts = Arr::wrap(Config::get('sso.saml.sp.entity_id'));
        if ($samlCerts === []) {
            $issues[] = 'SAML SP entity ID not set; will default to metadata URL.';
        }

        $provisioningEnabled = Config::get('sso.provisioning.enabled_by_default', false);
        $linkingEnabled = Config::get('sso.linking.enabled_by_default', false);
        if ($provisioningEnabled || $linkingEnabled) {
            $issues[] = 'Provisioning or linking is enabled by default; ensure you intend to allow automatic account creation/linking.';
        }

        if ($this->option('json')) {
            $payload = [
                'ok' => $issues === [],
                'issues' => $issues,
            ];
            $encoded = json_encode($payload, JSON_PRETTY_PRINT);
            $this->line($encoded !== false ? $encoded : '{}');
        } elseif ($issues === []) {
            $this->components->info('No blocking issues detected.');
        } else {
            foreach ($issues as $issue) {
                $this->components->warn($issue);
            }
        }

        if ($this->option('strict')) {
            return $issues === [] ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
