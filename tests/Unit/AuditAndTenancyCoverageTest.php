<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\AuditContextSanitizer;
use CreativeCrafts\LaravelSso\Core\Tenancy\CompositeTenantResolver;
use CreativeCrafts\LaravelSso\Core\Tenancy\RouteParamTenantResolver;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentTenantRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

it('sanitizes nested sensitive values and non scalar debug types', function (): void {
    $sanitizer = new AuditContextSanitizer(new ConfigRepository([
        'sso.audit.extended_context' => true,
        'sso.audit.subject_hash_algo' => 'sha256',
        'sso.audit.subject_hint_length' => 4,
        'sso.audit.string_value_max_length' => 10,
        'sso.audit.max_array_items' => 5,
        'sso.audit.max_claim_keys' => 5,
    ]));

    $result = new CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult(
        authenticated: true,
        canonicalClaims: new CreativeCrafts\LaravelSso\Core\Dto\Claims(
            subject: 'subject-with-long-value',
            email: 'user@example.test',
            displayName: null,
            emailVerified: null,
            groups: [],
            normalized: ['email' => 'user@example.test'],
        ),
        subject: 'subject-with-long-value',
        claims: [
            '' => 'ignored',
            'access_token' => 'secret-token',
            'note' => 'plain-text-value',
            'meta' => ['nested' => 'value'],
            'object' => new stdClass(),
        ],
        context: ['driver' => 'oidc'],
    );

    $context = $sanitizer->sanitizeSuccess('oidc', $result);

    expect($context['extended']['claims']['access_token'])->toBe('[redacted]')
        ->and($context['extended']['claims']['object'])->toBe('stdClass')
        ->and($context['subject_hint'])->toBe('subj...');
});

it('uses exception codes in failure audit context when present', function (): void {
    $sanitizer = new AuditContextSanitizer(new ConfigRepository([
        'sso.audit.extended_context' => false,
        'sso.audit.subject_hash_algo' => 'sha256',
    ]));

    $exception = new RuntimeException('failed', 418);

    $context = $sanitizer->sanitizeFailure($exception, 'state', 'saml');

    expect($context['error_code'])->toBe('418');
});

it('returns the first resolved tenant from composite resolvers', function (): void {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Route Tenant',
        'metadata' => [],
    ]);

    $request = Request::create('/sso/' . $tenant->ulid . '/connection/redirect', 'GET');
    $request->setRouteResolver(static fn () => new class ($tenant->ulid) {
        public function __construct(private string $tenantUlid)
        {
        }

        public function parameter(string $name): ?string
        {
            return $name === 'tenant' ? $this->tenantUlid : null;
        }
    });

    $resolver = new CompositeTenantResolver([
        new RouteParamTenantResolver('tenant', new EloquentTenantRepository()),
    ], throwIfMissing: false);

    expect($resolver->resolve($request)?->id)->toBe($tenant->id);
});

it('throws when composite resolution is configured to require a tenant', function (): void {
    $resolver = new CompositeTenantResolver([], throwIfMissing: true);

    expect(fn () => $resolver->resolve(Request::create('/sso/start', 'GET')))
        ->toThrow(TenantResolutionFailed::class);
});
