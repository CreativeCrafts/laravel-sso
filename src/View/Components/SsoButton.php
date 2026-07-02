<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class SsoButton extends Component
{
    public function __construct(
        public string $tenant,
        public string $connection,
        public string $label = 'Sign in with SSO',
        public ?string $redirectTo = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'tenant' => $this->tenant,
            'connection' => $this->connection,
            'label' => $this->label,
            'redirectTo' => $this->redirectTo,
            'href' => $this->url(),
        ];
    }

    public function render(): View
    {
        return $this->view('laravel-sso::components.sso-button');
    }

    public function url(): string
    {
        return sso_redirect_url($this->tenant, $this->connection, $this->redirectTo);
    }
}
