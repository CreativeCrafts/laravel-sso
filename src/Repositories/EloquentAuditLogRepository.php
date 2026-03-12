<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Repositories;

use CreativeCrafts\LaravelSso\Contracts\Repositories\AuditLogRepository;
use CreativeCrafts\LaravelSso\Models\AuditLog;

final class EloquentAuditLogRepository implements AuditLogRepository
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): AuditLog
    {
        return AuditLog::query()->create($attributes);
    }
}
