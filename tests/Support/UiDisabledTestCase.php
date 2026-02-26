<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use CreativeCrafts\LaravelSso\Tests\TestCase;

abstract class UiDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sso.routes.enabled', true);
        $app['config']->set('sso.ui.enabled', false);
    }
}
