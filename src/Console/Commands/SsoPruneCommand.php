<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Models\AuditLog;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class SsoPruneCommand extends Command
{
    private const CHUNK_SIZE = 1000;

    protected $signature = 'sso:prune {--attempts-days=7} {--audit-days=30}';

    protected $description = 'Prune stale SSO auth attempts and audit logs.';

    public function handle(): int
    {
        $attemptsDays = $this->optionInt('attempts-days');
        $auditDays = $this->optionInt('audit-days');

        $now = Carbon::now();

        $removedAttempts = 0;
        $removedAudits = 0;

        if ($attemptsDays > 0) {
            $removedAttempts = $this->chunkedDelete(
                AuthAttempt::query()->where('created_at', '<', $now->copy()->subDays($attemptsDays)),
            );
        }

        if ($auditDays > 0) {
            $removedAudits = $this->chunkedDelete(
                AuditLog::query()->where('created_at', '<', $now->copy()->subDays($auditDays)),
            );
        }

        $this->components->info(sprintf(
            'Pruned %d auth attempts and %d audit logs.',
            $removedAttempts,
            $removedAudits,
        ));

        return self::SUCCESS;
    }

    /**
     * @param Builder<AuthAttempt>|Builder<AuditLog> $query
     */
    private function chunkedDelete(Builder $query): int
    {
        $totalDeleted = 0;

        do {
            $deleted = $query->limit(self::CHUNK_SIZE)->delete();
            $deleted = is_int($deleted) ? $deleted : 0;
            $totalDeleted += $deleted;
        } while ($deleted >= self::CHUNK_SIZE);

        return $totalDeleted;
    }

    private function optionInt(string $name): int
    {
        $raw = $this->option($name);

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            return (int) $raw;
        }

        return 0;
    }
}
