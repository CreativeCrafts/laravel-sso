<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class SsoInstallCommand extends Command
{
    protected $signature = 'sso:install {--force : Overwrite existing published files}';

    protected $description = 'Publish Laravel SSO config, migrations, and UI assets with sensible defaults.';

    public function handle(): int
    {
        $this->components->info('Publishing Laravel SSO assets...');

        $params = [
            '--tag' => ['sso-config', 'sso-migrations', 'sso-ui'],
            '--force' => $this->option('force'),
        ];

        Artisan::call('vendor:publish', $params);

        $this->components->info('Running database migrations...');
        Artisan::call('migrate', ['--force' => true]);

        $this->components->info('Laravel SSO installation complete.');

        return self::SUCCESS;
    }
}
