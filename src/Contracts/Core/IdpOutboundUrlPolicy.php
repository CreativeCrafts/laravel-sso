<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Core;

interface IdpOutboundUrlPolicy
{
    public function assertTrustedForRequest(string $url, string $field): void;
}
