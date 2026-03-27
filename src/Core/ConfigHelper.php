<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

final class ConfigHelper
{
    public static function positiveInt(string $key, int $default): int
    {
        $value = config($key);

        if (is_int($value) && $value > 0) {
            return $value;
        }

        return $default;
    }
}
