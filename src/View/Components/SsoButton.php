<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class SsoButton extends Component
{
    private const VIEW = 'laravel-sso::components.sso-button';

    public function __construct(
        public string $tenant,
        public string $connection,
        public string $label = 'Sign in with SSO',
    ) {
    }

    public function render(): View
    {
        /** @var View $view */
        $view = app('view')->make(self::VIEW);

        return $view;
    }

    public function url(): string
    {
        return sso_redirect_url($this->tenant, $this->connection);
    }
}
