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
            '--tag' => ['laravel-sso-config', 'laravel-sso-migrations', 'sso-ui'],
            '--force' => $this->option('force'),
        ];

        $publishExit = Artisan::call('vendor:publish', $params);

        if ($publishExit !== 0) {
            $this->components->error('Publishing assets failed.');
            return self::FAILURE;
        }

        $this->components->info('Running database migrations...');
        $migrateExit = Artisan::call('migrate', ['--force' => true]);

        if ($migrateExit !== 0) {
            $this->components->error('Database migrations failed.');
            return self::FAILURE;
        }

        $this->components->info('Laravel SSO installation complete.');

        return self::SUCCESS;
    }
}
