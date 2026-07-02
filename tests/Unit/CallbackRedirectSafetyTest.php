<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\SafeRedirectValidator;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use Illuminate\Http\Request;

it('allows safe local callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('/dashboard'))
        ->toBe('/dashboard');
});

it('allows same-origin absolute callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('https://app.example.test/dashboard'))
        ->toBe('https://app.example.test/dashboard');
});

it('rejects external absolute callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('https://evil.example/dashboard'))
        ->toBe('/');
});

it('rejects literal protocol-relative callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('//evil.example/dashboard'))
        ->toBe('/');
});

it('rejects encoded protocol-relative callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('/%2F%2Fevil.example/dashboard'))
        ->toBe('/');
});

it('rejects encoded backslash callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('/%5Cevil.example/dashboard'))
        ->toBe('/');
});

it('rejects literal backslash callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('/\\evil.example/dashboard'))
        ->toBe('/');
});

it('rejects encoded control-character callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest('/dashboard%0ASet-Cookie:%20bad=true'))
        ->toBe('/');
});

it('rejects literal control-character callback redirects', function (): void {
    expect(resolveCallbackRedirectForTest("/dashboard\nSet-Cookie: bad=true"))
        ->toBe('/');
});

function resolveCallbackRedirectForTest(string $redirectTo): string
{
    $validator = new SafeRedirectValidator();
    $request = Request::create('https://app.example.test/sso/tenant/1/callback', 'GET');

    $attempt = new AuthAttempt();
    $attempt->forceFill([
        'redirect_to' => $redirectTo,
    ]);

    return $validator->resolveStoredRedirect($request, $attempt->redirect_to);
}
