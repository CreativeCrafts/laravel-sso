<?php

declare(strict_types=1);

it('keeps README local documentation links valid', function (): void {
    $root = dirname(__DIR__, 2);
    $readmePath = $root . '/README.md';

    expect(is_file($readmePath))->toBeTrue();

    $readme = file_get_contents($readmePath);
    expect($readme)->toBeString();

    preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', (string) $readme, $matches);

    $missing = [];

    foreach ($matches[1] as $target) {
        $target = trim((string) $target);

        if ($target === '' || str_starts_with($target, '#') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1) {
            continue;
        }

        if (str_starts_with($target, '../../')) {
            continue;
        }

        $path = explode('#', $target, 2)[0];
        $path = explode('?', $path, 2)[0];

        if ($path === '') {
            continue;
        }

        $absolutePath = realpath($root . '/' . $path);

        if ($absolutePath === false || !str_starts_with($absolutePath, $root) || !file_exists($absolutePath)) {
            $missing[] = $target;
        }
    }

    expect($missing)->toBe([]);
});
