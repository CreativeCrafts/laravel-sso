<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;

final readonly class AuditContextSanitizer
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeSuccess(string $protocol, DriverCallbackResult $result): array
    {
        $claimKeys = $this->claimKeys($result->claims);

        $context = [
            'status' => 'succeeded',
            'protocol' => $protocol,
            'authenticated' => $result->authenticated,
            'error_code' => $result->error,
            'subject_hint' => $this->truncateIdentifier($result->subject),
            'subject_hash' => $this->hashIdentifier($result->subject),
            'claim_keys' => $claimKeys,
            'claim_count' => count($claimKeys),
        ];

        foreach ($this->safeContext($result->context) as $key => $value) {
            $context[$key] = $value;
        }

        if ($this->extendedContextEnabled()) {
            $context['extended'] = [
                'claims' => $this->sanitizeArray($result->claims, true),
                'driver_context' => $this->sanitizeArray($result->context, true),
            ];
        }

        return array_filter(
            $context,
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeFailure(Throwable $exception, string $state, ?string $protocol = null): array
    {
        $context = [
            'status' => 'failed',
            'protocol' => $protocol,
            'error_code' => $this->errorCode($exception),
            'state_hash' => $this->hashIdentifier($state),
        ];

        if ($this->extendedContextEnabled()) {
            $context['extended'] = [
                'exception' => $exception::class,
                'message' => $this->truncateString($exception->getMessage()),
            ];
        }

        return array_filter(
            $context,
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, bool|string|int|float|null>
     */
    private function safeContext(array $context): array
    {
        $allowed = [
            'driver',
            'userinfo_used',
            'response_signature_valid',
            'assertion_signature_valid',
            'binding',
            'pkce',
        ];

        $safe = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];

            if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
                $safe[$key] = is_string($value) ? $this->truncateString($value) : $value;
            }
        }

        return $safe;
    }

    /**
     * @param array<string, mixed> $claims
     * @return list<string>
     */
    private function claimKeys(array $claims): array
    {
        $keys = [];

        foreach (array_keys($claims) as $key) {
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        sort($keys);

        return array_slice($keys, 0, $this->maxClaimKeys());
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values, bool $extended): array
    {
        $sanitized = [];

        foreach (array_slice($values, 0, $this->maxArrayItems(), true) as $key => $value) {
            if ($key === '') {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($key, $value, $extended);
        }

        return $sanitized;
    }

    private function sanitizeValue(string $key, mixed $value, bool $extended): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            if (!$extended) {
                return [
                    'type' => 'array',
                    'count' => count($value),
                ];
            }

            /** @var array<string, mixed> $value */
            return $this->sanitizeArray($value, true);
        }

        if (is_string($value)) {
            if (str_contains($value, '@')) {
                return $this->maskEmail($value);
            }

            return $this->truncateString($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return get_debug_type($value);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach ([
            'token',
            'secret',
            'assertion',
            'samlresponse',
            'saml_response',
            'raw_claim',
            'private_key',
            'certificate',
            'password',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function errorCode(Throwable $exception): string
    {
        $code = $exception->getCode();

        if (is_string($code) && $code !== '') {
            return $code;
        }

        if (is_int($code) && $code !== 0) {
            return (string) $code;
        }

        return class_basename($exception);
    }

    private function truncateIdentifier(?string $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $length = $this->subjectHintLength();

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . '...';
    }

    private function hashIdentifier(?string $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $algo = $this->config->get('sso.audit.subject_hash_algo', 'sha256');
        $algo = is_string($algo) && in_array($algo, hash_algos(), true) ? $algo : 'sha256';

        return hash($algo, $value);
    }

    private function truncateString(string $value): string
    {
        $max = $this->stringValueMaxLength();

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max) . '...';
    }

    private function maskEmail(string $value): string
    {
        $parts = explode('@', $value, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return $this->truncateString($value);
        }

        $local = $parts[0];
        $maskedLocal = mb_substr($local, 0, 1) . '***';

        return $maskedLocal . '@' . $parts[1];
    }

    private function extendedContextEnabled(): bool
    {
        return (bool) $this->config->get('sso.audit.extended_context', false);
    }

    private function subjectHintLength(): int
    {
        $value = $this->config->get('sso.audit.subject_hint_length', 12);

        return is_int($value) && $value > 0 ? $value : 12;
    }

    private function stringValueMaxLength(): int
    {
        $value = $this->config->get('sso.audit.string_value_max_length', 80);

        return is_int($value) && $value > 0 ? $value : 80;
    }

    private function maxClaimKeys(): int
    {
        $value = $this->config->get('sso.audit.max_claim_keys', 20);

        return is_int($value) && $value > 0 ? $value : 20;
    }

    private function maxArrayItems(): int
    {
        $value = $this->config->get('sso.audit.max_array_items', 20);

        return is_int($value) && $value > 0 ? $value : 20;
    }
}
