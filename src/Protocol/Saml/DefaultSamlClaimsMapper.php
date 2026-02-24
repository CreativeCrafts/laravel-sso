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

        $email = $this->firstAttributeValue($attributes, [
          'email',
          'mail',
          'EmailAddress',
          'upn',
          'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
          'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/upn',
        ]);

        $displayName = $this->firstAttributeValue($attributes, [
          'name',
          'displayName',
          'cn',
          'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        ]);

        if ($displayName === null) {
            $given = $this->firstAttributeValue($attributes, ['givenName', 'firstName']);
            $sn = $this->firstAttributeValue($attributes, ['sn', 'surname', 'lastName']);

            $computed = trim(implode(' ', array_filter([$given, $sn], static fn ($v): bool => is_string($v) && $v !== '')));
            $displayName = $computed !== '' ? $computed : null;
        }

        $groups = $this->groups($attributes);

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
            if (!is_array($values)) {
                continue;
            }
            if ($values === []) {
                continue;
            }

            $value = trim($values[0]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<int, string>> $attributes
     * @return array<int, string>
     */
    private function groups(array $attributes): array
    {
        $candidateKeys = [
          'groups',
          'memberOf',
          'roles',
          'http://schemas.microsoft.com/ws/2008/06/identity/claims/role',
        ];

        $values = [];

        foreach ($candidateKeys as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }

            foreach ($attributes[$key] as $v) {
                $v = trim($v);
                if ($v === '') {
                    continue;
                }

                // Split commonly delimited formats while preserving single DN values.
                if (str_contains($v, ';')) {
                    foreach (explode(';', $v) as $p) {
                        $p = trim($p);
                        if ($p !== '') {
                            $values[] = $p;
                        }
                    }
                    continue;
                }

                if (str_contains($v, ',') && !str_contains($v, '=')) {
                    foreach (explode(',', $v) as $p) {
                        $p = trim($p);
                        if ($p !== '') {
                            $values[] = $p;
                        }
                    }
                    continue;
                }

                $values[] = $v;
            }

            if ($values !== []) {
                break;
            }
        }

        return array_values(array_unique($values));
    }
}
