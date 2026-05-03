<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

interface HostnameResolver
{
    /**
     * @return array<int, string> Resolved IPv4 and IPv6 addresses.
     */
    public function resolve(string $hostname): array;
}
