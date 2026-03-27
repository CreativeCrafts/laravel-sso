<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('reports ok status when defaults valid', function (): void {
    $this->artisan('sso:doctor')
        ->assertExitCode(0);
});
