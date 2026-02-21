<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Tests\Support;

use RuntimeException;

final class OidcTestJwt
{
    /**
     * @return array{private: string, public: string}
     */
    public static function generateRsaKeypair(int $bits = 2048): array
    {
        $key = openssl_pkey_new([
          'private_key_bits' => $bits,
          'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new RuntimeException('Unable to generate RSA keypair');
        }

        $privatePem = '';
        if (!openssl_pkey_export($key, $privatePem) || $privatePem === '') {
            throw new RuntimeException('Unable to export RSA private key');
        }

        $details = openssl_pkey_get_details($key);

        if (!is_array($details) || !isset($details['key']) || !is_string($details['key']) || $details['key'] === '') {
            throw new RuntimeException('Unable to export RSA public key');
        }

        return [
          'private' => $privatePem,
          'public' => $details['key'],
        ];
    }

    public static function jwtRs256(array $payload, string $privateKeyPem, string $kid): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid];

        $h = self::b64url(json_encode($header, JSON_THROW_ON_ERROR));
        $p = self::b64url(json_encode($payload, JSON_THROW_ON_ERROR));

        $data = $h . '.' . $p;

        $sig = '';
        $ok = openssl_sign($data, $sig, $privateKeyPem, OPENSSL_ALGO_SHA256);

        if ($ok !== true || $sig === '') {
            throw new RuntimeException('Unable to sign JWT (RS256)');
        }

        return $data . '.' . self::b64url($sig);
    }

    public static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * @return array{keys: array<int, array<string, mixed>>}
     */
    public static function jwksFromPublicKey(string $publicKeyPem, string $kid): array
    {
        $pub = openssl_pkey_get_public($publicKeyPem);

        if ($pub === false) {
            throw new RuntimeException('Unable to load public key');
        }

        $details = openssl_pkey_get_details($pub);

        if (!is_array($details) || !isset($details['rsa']) || !is_array($details['rsa'])) {
            throw new RuntimeException('Unable to read RSA public key details');
        }

        /** @var array<string, mixed> $rsa */
        $rsa = $details['rsa'];

        if (!isset($rsa['n'], $rsa['e']) || !is_string($rsa['n']) || !is_string($rsa['e'])) {
            throw new RuntimeException('RSA modulus/exponent missing');
        }

        return [
          'keys' => [
            [
              'kty' => 'RSA',
              'use' => 'sig',
              'alg' => 'RS256',
              'kid' => $kid,
              'n' => self::b64url($rsa['n']),
              'e' => self::b64url($rsa['e']),
            ],
          ],
        ];
    }
}
