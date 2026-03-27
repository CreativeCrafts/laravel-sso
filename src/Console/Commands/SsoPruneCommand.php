<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Console\Commands;

use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SsoPruneCommand extends Command
{
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
            $attemptsResult = AuthAttempt::query()
                ->where('created_at', '<', $now->copy()->subDays($attemptsDays))
                ->delete();

            $removedAttempts = is_int($attemptsResult) ? $attemptsResult : 0;
        }

        if ($auditDays > 0) {
            $auditResult = AuditLog::query()
                ->where('created_at', '<', $now->copy()->subDays($auditDays))
                ->delete();

            $removedAudits = is_int($auditResult) ? $auditResult : 0;
        }

        $this->components->info(sprintf(
            'Pruned %d auth attempts and %d audit logs.',
            $removedAttempts,
            $removedAudits,
        ));

        return self::SUCCESS;
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
