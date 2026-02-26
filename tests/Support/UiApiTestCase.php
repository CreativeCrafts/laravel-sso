<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use Illuminate\Support\Facades\Gate;

abstract class UiApiTestCase extends UiEnabledTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Allow requests through the `can:<gate>` middleware deterministically.
        $uiGate = (string)$this->app['config']->get('sso.ui.gate', 'manageSso');

        // IMPORTANT: `can:` passes the (possibly null) user as the first argument.
        Gate::define($uiGate, static fn ($user = null): bool => true);

        // Defensive: allow any other ability checks in these package feature tests.
        Gate::before(static fn ($user, string $ability): bool => true);
    }
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // These package feature tests should not depend on a host app auth setup.
        // Ensure the UI route group does not include `auth`.
        $app['config']->set('sso.ui.middleware', ['web']);
    }
}
