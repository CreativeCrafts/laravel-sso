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
            email: self::redactEmail($result->email),
            displayName: self::redactString($result->displayName),
            error: $result->error,
        );
    }

    private static function redactEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }

        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = mb_substr($local, 0, 1) . '***';

        return $maskedLocal . '@' . $domain;
    }

    private static function redactString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return mb_substr($value, 0, 1) . '***';
    }
}
