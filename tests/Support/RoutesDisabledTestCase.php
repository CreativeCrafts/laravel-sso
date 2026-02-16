<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use CreativeCrafts\LaravelSso\Tests\TestCase;

abstract class RoutesDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sso.routes.enabled', false);
        $app['config']->set('sso.ui.enabled', false);
    }
}