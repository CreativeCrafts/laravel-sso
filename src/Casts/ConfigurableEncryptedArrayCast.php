<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use RuntimeException;

/**
 * @implements CastsAttributes<array<string, mixed>|null, mixed>
 */
final class ConfigurableEncryptedArrayCast implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return $this->normalizeStringKeyedArray($value);
        }

        if (!$this->encryptionEnabled()) {
            return $this->decodeJson($value);
        }

        try {
            $decrypted = Crypt::decryptString($value);

            return $this->decodeJson($decrypted);
        } catch (\Throwable) {
            return $this->decodeJson($value);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new RuntimeException('Expected array value for encrypted claims cast.');
        }

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        if (!$this->encryptionEnabled()) {
            return $encoded;
        }

        return Crypt::encryptString($encoded);
    }

    private function encryptionEnabled(): bool
    {
        return (bool) config('sso.claims.encrypt_persisted', true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $value): ?array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $this->normalizeStringKeyedArray($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeStringKeyedArray(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                return null;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }
}
