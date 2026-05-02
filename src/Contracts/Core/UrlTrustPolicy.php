<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

interface UrlTrustPolicy
{
    public function assertTrusted(string $url, string $field): void;

    public function isTrusted(string $url): bool;
}
