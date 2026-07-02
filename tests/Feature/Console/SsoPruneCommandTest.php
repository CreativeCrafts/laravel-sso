<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Console\Commands\SsoPruneCommand;
use CreativeCrafts\LaravelSso\Models\AuditLog;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(TestCase::class);

it('prunes old auth attempts and audit logs', function () {
    $tenant = Tenant::query()->create([
        'ulid' => (string) Str::ulid(),
        'name' => 'Acme',
    ]);

    AuthAttempt::query()->create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oidc',
        'state' => 'old-state',
        'expires_at' => Carbon::now()->subDays(10),
        'context' => [],
    ]);

    AuthAttempt::query()->create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oidc',
        'state' => 'new-state',
        'expires_at' => Carbon::now()->addHour(),
        'context' => [],
    ]);

    $oldAudit = AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'event' => 'old',
        'context' => [],
    ]);

    $recentAudit = AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'event' => 'new',
        'context' => [],
    ]);

    $oldAudit->forceFill(['created_at' => Carbon::now()->subDays(40)])->save();
    $recentAudit->forceFill(['created_at' => Carbon::now()->subDay()])->save();

    $exit = Artisan::call(SsoPruneCommand::class, [
        '--attempts-days' => 5,
        '--audit-days' => 30,
    ]);

    expect($exit)->toBe(0);
    expect(AuthAttempt::query()->count())->toBe(1);
    expect(AuditLog::query()->count())->toBe(1);
});
