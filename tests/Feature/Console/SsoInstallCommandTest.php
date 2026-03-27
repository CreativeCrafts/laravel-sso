<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('runs sso:install without error', function (): void {
    $this->artisan('sso:install --no-interaction')
        ->assertExitCode(0);
});
