<?php

namespace CreativeCrafts\LaravelSso\Commands;

use Illuminate\Console\Command;

class LaravelSsoCommand extends Command
{
    public $signature = 'laravel-sso';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
