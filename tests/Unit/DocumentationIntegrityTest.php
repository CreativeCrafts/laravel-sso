<?php

declare(strict_types=1);

it('keeps README local documentation links valid', function (): void {
    assertMarkdownLinksValid(packageRoot() . '/README.md');
});

it('keeps docs directory local links valid', function (): void {
    $docsRoot = packageRoot() . '/docs';

    expect(is_dir($docsRoot))->toBeTrue();

    $files = glob($docsRoot . '/*.md');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        assertMarkdownLinksValid($file);
    }
});

it('keeps SECURITY.md local links valid', function (): void {
    assertMarkdownLinksValid(packageRoot() . '/SECURITY.md');
});

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @param non-empty-string $markdownPath
 */
function assertMarkdownLinksValid(string $markdownPath): void
{
    $root = packageRoot();

    expect(is_file($markdownPath))->toBeTrue();

    $markdown = file_get_contents($markdownPath);
    expect($markdown)->toBeString();

    preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', (string) $markdown, $matches);

    $githubVirtualTargets = [
        '../../contributors',
        '../../security/policy',
    ];

    $missing = [];
    $markdownDir = dirname($markdownPath);

    foreach ($matches[1] as $target) {
        $target = trim((string) $target);

        if ($target === '' || str_starts_with($target, '#') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1) {
            continue;
        }

        $path = explode('#', $target, 2)[0];
        $path = explode('?', $path, 2)[0];

        if ($path === '' || in_array($path, $githubVirtualTargets, true)) {
            continue;
        }

        if (str_starts_with($path, '/')) {
            $candidatePath = $path;
        } elseif (str_starts_with($path, '../') || str_starts_with($path, './')) {
            $candidatePath = $markdownDir . '/' . $path;
        } else {
            $candidatePath = $markdownDir . '/' . $path;
        }

        $absolutePath = realpath($candidatePath);

        if ($absolutePath === false || !str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR) || !file_exists($absolutePath)) {
            $missing[] = $target . ' (in ' . basename($markdownPath) . ')';
        }
    }

    expect($missing)->toBe([]);
}
