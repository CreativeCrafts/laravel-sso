<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Requests\Admin\Rules;

use CreativeCrafts\LaravelSso\Core\TenantRouteKey;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class UniqueTenantUlid implements ValidationRule
{
    public function __construct(
        private ?int $ignoreTenantId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        $query = Tenant::query();

        if (TenantRouteKey::looksLikeUlid($value)) {
            $query->whereRaw('UPPER(ulid) = ?', [strtoupper(trim($value))]);
        } else {
            $query->where('ulid', trim($value));
        }

        if ($this->ignoreTenantId !== null) {
            $query->where('id', '!=', $this->ignoreTenantId);
        }

        if ($query->exists()) {
            $fail('The :attribute has already been taken.');
        }
    }
}
