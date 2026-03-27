<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use Throwable;

interface AuditContextSanitizer
{
    /**
     * @return array<string, mixed>
     */
    public function sanitizeSuccess(string $protocol, DriverCallbackResult $result): array;

    /**
     * @return array<string, mixed>
     */
    public function sanitizeFailure(Throwable $exception, string $state, ?string $protocol = null): array;
}
