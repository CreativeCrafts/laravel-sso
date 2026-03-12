<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Repositories;

use CreativeCrafts\LaravelSso\Models\AuditLog;

interface AuditLogRepository
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): AuditLog;
}
