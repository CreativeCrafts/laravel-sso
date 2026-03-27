<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\View\Components;

use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class SsoButton extends Component
{
    public function __construct(
        public string $tenant,
        public string $connection,
        public string $label = 'Sign in with SSO',
    ) {
    }

    public function render(): View
    {
        return view('laravel-sso::components.sso-button');
    }

    public function url(): string
    {
        return sso_redirect_url($this->tenant, $this->connection);
    }
}
