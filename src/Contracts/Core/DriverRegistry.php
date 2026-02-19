<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

interface DriverRegistry
{
    public function get(string $protocol): SsoDriver;
}
