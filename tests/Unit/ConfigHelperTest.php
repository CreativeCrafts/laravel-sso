<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\ConfigHelper;
use Illuminate\Support\Facades\Config;

it('returns configured positive integers or the default', function (): void {
    Config::set('sso.test.positive', 15);

    expect(ConfigHelper::positiveInt('sso.test.positive', 5))->toBe(15)
        ->and(ConfigHelper::positiveInt('sso.test.missing', 5))->toBe(5)
        ->and(ConfigHelper::positiveInt('sso.test.zero', 5))->toBe(5);
});

it('ignores non positive configured integers', function (): void {
    Config::set('sso.test.positive', -1);
    Config::set('sso.test.string', 'abc');

    expect(ConfigHelper::positiveInt('sso.test.positive', 9))->toBe(9)
        ->and(ConfigHelper::positiveInt('sso.test.string', 9))->toBe(9);
});
