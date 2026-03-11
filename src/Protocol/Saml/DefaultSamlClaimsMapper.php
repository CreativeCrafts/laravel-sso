<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsMapper;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Exceptions\SamlClaimsNormalizationFailed;

final class DefaultSamlClaimsMapper implements SamlClaimsMapper
{
    public function map(string $nameId, array $attributes): Claims
    {
        $subject = trim($nameId);

        if ($subject === '') {
            throw SamlClaimsNormalizationFailed::missingNameId();
        }

        $emailKeys = $this->mappingKeys('email', [
          'email',
          'mail',
          'EmailAddress',
          'upn',
          'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
          'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/upn',
        ]);

        $displayNameKeys = $this->mappingKeys('display_name', [
          'name',
          'displayName',
          'cn',
          'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        ]);

        $givenNameKeys = $this->mappingKeys('given_name', [
          'givenName',
          'firstName',
        ]);

        $surnameKeys = $this->mappingKeys('surname', [
          'sn',
          'surname',
          'lastName',
        ]);

        $groupKeys = $this->mappingKeys('groups', [
          'groups',
          'memberOf',
          'roles',
          'http://schemas.microsoft.com/ws/2008/06/identity/claims/role',
        ]);

        $email = $this->firstAttributeValue($attributes, $emailKeys);
        $displayName = $this->firstAttributeValue($attributes, $displayNameKeys);

        if ($displayName === null) {
            $given = $this->firstAttributeValue($attributes, $givenNameKeys);
            $surname = $this->firstAttributeValue($attributes, $surnameKeys);

            $computed = trim(implode(' ', array_filter([$given, $surname], static fn ($value): bool => is_string($value) && $value !== '')));
            $displayName = $computed !== '' ? $computed : null;
        }

        $groups = $this->groups($attributes, $groupKeys);

        return new Claims(
            subject: $subject,
            email: $email,
            displayName: $displayName,
            emailVerified: null,
            groups: $groups,
            normalized: [
            'raw_saml' => [
              'name_id' => $subject,
              'attributes' => $attributes,
            ],
          ],
        );
    }

    /**
     * @param array<string, array<int, string>> $attributes
     * @param array<int, string> $keys
     */
    private function firstAttributeValue(array $attributes, array $keys): ?string
    {
        foreach ($keys as $key) {
            $values = $attributes[$key] ?? null;

            if (!is_array($values) || $values === []) {
                continue;
            }

            $value = trim((string)$values[0]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<int, string>> $attributes
     * @param array<int, string> $candidateKeys
     * @return array<int, string>
     */
    private function groups(array $attributes, array $candidateKeys): array
    {
        $values = [];

        foreach ($candidateKeys as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }

            foreach ($attributes[$key] as $value) {
                $value = trim((string)$value);

                if ($value === '') {
                    continue;
                }

                if (str_contains($value, ';')) {
                    foreach (explode(';', $value) as $part) {
                        $part = trim($part);

                        if ($part !== '') {
                            $values[] = $part;
                        }
                    }

                    continue;
                }

                if (str_contains($value, ',') && !str_contains($value, '=')) {
                    foreach (explode(',', $value) as $part) {
                        $part = trim($part);

                        if ($part !== '') {
                            $values[] = $part;
                        }
                    }

                    continue;
                }

                $values[] = $value;
            }

            if ($values !== []) {
                break;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function mappingKeys(string $key, array $default): array
    {
        $raw = config('sso.saml.attribute_mapping.' . $key);

        if (!is_array($raw)) {
            return $default;
        }

        $keys = [];

        foreach ($raw as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $keys[] = $value;
        }

        return $keys === [] ? $default : array_values(array_unique($keys));
    }
}
