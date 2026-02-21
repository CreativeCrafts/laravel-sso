<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Protocol\Oidc\DefaultOidcClaimsNormalizer;
use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('normalizes subject/email/displayName deterministically', function () {
    $normalizer = new DefaultOidcClaimsNormalizer();

    $claims = [
      'sub' => 'sub-1',
      'upn' => 'user@example.test',
      'preferred_username' => 'user.one',
      'email_verified' => true,
      'groups' => ['admin', 'staff'],
    ];

    $canonical = $normalizer->normalize($claims);

    expect($canonical->subject)
      ->toBe('sub-1')
      ->and($canonical->email)->toBe('user@example.test')
      ->and($canonical->displayName)->toBe('user.one')
      ->and($canonical->emailVerified)->toBeTrue()
      ->and($canonical->groups)->toBe(['admin', 'staff']);
});
