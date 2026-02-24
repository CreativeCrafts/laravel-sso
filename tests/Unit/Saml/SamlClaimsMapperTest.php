<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Protocol\Saml\DefaultSamlClaimsMapper;

it('maps common email/name/groups attributes into canonical claims', function () {
    $mapper = new DefaultSamlClaimsMapper();

    $claims = $mapper->map('sub-123', [
      'mail' => ['user@example.test'],
      'displayName' => ['User One'],
      'groups' => ['admin', 'dev'],
    ]);

    expect($claims->subject)
      ->toBe('sub-123')
      ->and($claims->email)->toBe('user@example.test')
      ->and($claims->displayName)->toBe('User One')
      ->and($claims->groups)->toBe(['admin', 'dev']);
});

it('derives displayName from givenName + sn when displayName is missing', function () {
    $mapper = new DefaultSamlClaimsMapper();

    $claims = $mapper->map('sub-123', [
      'givenName' => ['User'],
      'sn' => ['One'],
      'email' => ['user@example.test'],
    ]);

    expect($claims->displayName)->toBe('User One');
});

it('splits delimited groups formats', function () {
    $mapper = new DefaultSamlClaimsMapper();

    $claims = $mapper->map('sub-123', [
      'memberOf' => ['admin;dev;ops'],
    ]);

    expect($claims->groups)->toBe(['admin', 'dev', 'ops']);
});
