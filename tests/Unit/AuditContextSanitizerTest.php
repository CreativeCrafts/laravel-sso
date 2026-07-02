<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\AuditContextSanitizer;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use Illuminate\Config\Repository as ConfigRepository;

it('sanitizes successful callback audit context', function (): void {
    $sanitizer = new AuditContextSanitizer(new ConfigRepository([
        'sso.audit.extended_context' => false,
        'sso.audit.subject_hash_algo' => 'sha256',
        'sso.audit.subject_hint_length' => 8,
    ]));

    $result = new DriverCallbackResult(
        authenticated: true,
        canonicalClaims: new Claims(
            subject: 'sub-123',
            email: 'user@example.test',
            displayName: 'User',
            emailVerified: true,
            groups: ['staff'],
            normalized: ['email' => 'user@example.test', 'groups' => ['staff']],
        ),
        subject: 'sub-123',
        email: 'user@example.test',
        claims: ['email' => 'user@example.test', 'groups' => ['staff']],
        context: ['driver' => 'oidc'],
    );

    $context = $sanitizer->sanitizeSuccess('oidc', $result);

    expect($context)
        ->toHaveKeys(['status', 'protocol', 'subject_hash', 'subject_hint', 'claim_keys'])
        ->and($context['status'])->toBe('succeeded')
        ->and($context['subject_hint'])->toBeString();
});

it('includes extended context when enabled', function (): void {
    $sanitizer = new AuditContextSanitizer(new ConfigRepository([
        'sso.audit.extended_context' => true,
        'sso.audit.subject_hash_algo' => 'sha256',
        'sso.audit.subject_hint_length' => 8,
        'sso.audit.string_value_max_length' => 40,
        'sso.audit.max_claim_keys' => 10,
        'sso.audit.max_array_items' => 10,
    ]));

    $result = new DriverCallbackResult(
        authenticated: true,
        canonicalClaims: new Claims(
            subject: 'sub-123',
            email: 'user@example.test',
            displayName: null,
            emailVerified: null,
            groups: [],
            normalized: ['email' => 'user@example.test'],
        ),
        subject: 'sub-123',
        claims: ['email' => 'user@example.test'],
        context: ['note' => 'ok'],
    );

    $context = $sanitizer->sanitizeSuccess('oidc', $result);

    expect($context)->toHaveKey('extended')
        ->and($context['extended'])->toHaveKeys(['claims', 'driver_context']);
});

it('sanitizes failure audit context', function (): void {
    $sanitizer = new AuditContextSanitizer(new ConfigRepository([
        'sso.audit.extended_context' => true,
        'sso.audit.subject_hash_algo' => 'sha256',
    ]));

    $context = $sanitizer->sanitizeFailure(
        OidcIdTokenValidationFailed::make('bad token'),
        'state-value',
        'oidc',
    );

    expect($context)
        ->toMatchArray([
            'status' => 'failed',
            'protocol' => 'oidc',
        ])
        ->and($context)->toHaveKey('state_hash')
        ->and($context['extended']['exception'])->toBe(OidcIdTokenValidationFailed::class);
});
