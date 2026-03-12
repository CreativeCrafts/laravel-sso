<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Repositories\EloquentAuditLogRepository;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

it('creates audit logs through the repository', function () {
    expect(Schema::hasTable('sso_audit_logs'))->toBeTrue();

    $repository = new EloquentAuditLogRepository();

    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Tenant A',
    ]);

    $audit = $repository->create([
        'tenant_id' => $tenant->id,
        'identity_provider_id' => null,
        'connection_id' => null,
        'auth_attempt_id' => null,
        'event' => 'sso.callback.failed',
        'level' => 'warning',
        'context' => [
            'exception' => RuntimeException::class,
            'message' => 'Example failure.',
        ],
    ]);

    expect($audit->tenant_id)->toBe($tenant->id)
        ->and($audit->event)->toBe('sso.callback.failed')
        ->and($audit->context['exception'])->toBe(RuntimeException::class);
});
