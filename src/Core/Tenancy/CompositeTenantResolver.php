<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core\Tenancy;

use CreativeCrafts\LaravelSso\Contracts\Core\TenantResolver;
use CreativeCrafts\LaravelSso\Models\Tenant;
use Illuminate\Http\Request;

final readonly class CompositeTenantResolver implements TenantResolver
{
    /**
     * @param array<int, TenantResolver> $resolvers
     */
    public function __construct(
        private array $resolvers,
    ) {
    }

    public function resolve(Request $request): ?Tenant
    {
        foreach ($this->resolvers as $resolver) {
            $tenant = $resolver->resolve($request);

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        return null;
    }
}
