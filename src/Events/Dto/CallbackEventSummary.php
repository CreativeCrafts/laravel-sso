<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Events\Dto;

use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;

final readonly class CallbackEventSummary
{
    public function __construct(
        public string $protocol,
        public bool $authenticated,
        public ?string $subject = null,
        public ?string $email = null,
        public ?string $displayName = null,
        public ?string $error = null,
    ) {
    }

    public static function fromResult(string $protocol, DriverCallbackResult $result): self
    {
        return new self(
            protocol: $protocol,
            authenticated: $result->authenticated,
            subject: $result->subject,
            email: $result->email,
            displayName: $result->displayName,
            error: $result->error,
        );
    }
}
