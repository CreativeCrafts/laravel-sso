<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcClaimsNormalizer;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;

final class DefaultOidcClaimsNormalizer implements OidcClaimsNormalizer
{
    /**
     * @param array<string, mixed> $claims
     */
    public function normalize(array $claims): Claims
    {
        $subject = $this->firstNonEmptyString($claims, ['sub', 'subject', 'uid', 'user_id']);

        if ($subject === null) {
            throw OidcIdTokenValidationFailed::make('missing subject claim');
        }

        $email = $this->firstNonEmptyString($claims, ['email', 'upn', 'preferred_email']);

        $displayName = $this->firstNonEmptyString($claims, ['name', 'preferred_username', 'nickname']);

        if ($displayName === null) {
            $given = $this->firstNonEmptyString($claims, ['given_name', 'first_name']);
            $family = $this->firstNonEmptyString($claims, ['family_name', 'last_name']);

            $displayName = $this->joinName($given, $family);
        }

        $emailVerified = $claims['email_verified'] ?? null;
        $emailVerified = is_bool($emailVerified) ? $emailVerified : null;

        $groups = $this->stringArray($claims['groups'] ?? null);

        $normalized = [
          'sub' => $subject,
          'email' => $email,
          'name' => $displayName,
          'email_verified' => $emailVerified,
          'groups' => $groups,
        ];

        return new Claims(
            subject: $subject,
            email: $email,
            displayName: $displayName,
            emailVerified: $emailVerified,
            groups: $groups,
            normalized: $normalized,
        );
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<int, string> $keys
     */
    private function firstNonEmptyString(array $claims, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $claims[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function joinName(?string $given, ?string $family): ?string
    {
        $given = is_string($given) && $given !== '' ? $given : null;
        $family = is_string($family) && $family !== '' ? $family : null;

        if ($given === null && $family === null) {
            return null;
        }

        if ($given !== null && $family !== null) {
            return $given . ' ' . $family;
        }

        return $given ?? $family;
    }

    /**
     * @return array<int, string>
     */
    private function stringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
