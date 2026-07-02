<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin\Concerns;

use Illuminate\Support\Facades\Gate;

trait AuthorizesSsoAdmin
{
    public function authorizeSsoAdmin(): bool
    {
        $ability = config('sso.ui.gate', 'manageSso');
        $ability = is_string($ability) && $ability !== '' ? $ability : 'manageSso';

        if (!Gate::has($ability)) {
            return (bool) config('sso.ui.allow_missing_gate', false);
        }

        return Gate::allows($ability);
    }
}
