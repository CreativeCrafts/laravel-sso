<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Casts\ConfigurableEncryptedArrayCast;
use CreativeCrafts\LaravelSso\Models\ExternalIdentity;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

it('encrypts and decrypts persisted claims when encryption is enabled', function (): void {
    Config::set('sso.claims.encrypt_persisted', true);

    $cast = new ConfigurableEncryptedArrayCast();
    $model = new ExternalIdentity();
    $payload = ['email' => 'user@example.test', 'groups' => ['staff']];

    $stored = $cast->set($model, 'claims', $payload, []);

    expect($stored)->toBeString()
        ->and($stored)->not->toContain('user@example.test');

    $decoded = $cast->get($model, 'claims', $stored, []);

    expect($decoded)->toBe($payload);
});

it('stores plain json when encryption is disabled', function (): void {
    Config::set('sso.claims.encrypt_persisted', false);

    $cast = new ConfigurableEncryptedArrayCast();
    $model = new ExternalIdentity();

    $stored = $cast->set($model, 'claims', ['role' => 'admin'], []);

    expect($stored)->toBe('{"role":"admin"}');
});

it('falls back to plain json when ciphertext cannot be decrypted', function (): void {
    Config::set('sso.claims.encrypt_persisted', true);

    $cast = new ConfigurableEncryptedArrayCast();
    $model = new ExternalIdentity();
    $plain = '{"legacy":true}';

    $decoded = $cast->get($model, 'claims', $plain, []);

    expect($decoded)->toBe(['legacy' => true]);
});

it('returns null for invalid stored values', function (): void {
    $cast = new ConfigurableEncryptedArrayCast();
    $model = new ExternalIdentity();

    expect($cast->get($model, 'claims', null, []))->toBeNull()
        ->and($cast->get($model, 'claims', 'not-json', []))->toBeNull()
        ->and($cast->get($model, 'claims', Crypt::encryptString('not-json'), []))->toBeNull()
        ->and($cast->get($model, 'claims', ['already' => 'decoded'], []))->toBe(['already' => 'decoded'])
        ->and($cast->get($model, 'claims', [0 => 'bad-key'], []))->toBeNull();
});

it('stores null claims and rejects non array assignments', function (): void {
    $cast = new ConfigurableEncryptedArrayCast();
    $model = new ExternalIdentity();

    expect($cast->set($model, 'claims', null, []))->toBeNull();

    $cast->set($model, 'claims', 'invalid', []);
})->throws(RuntimeException::class);
