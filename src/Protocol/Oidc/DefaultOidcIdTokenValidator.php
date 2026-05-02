<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Oidc;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcIdTokenValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcJwksFetcher;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use JsonException;

final readonly class DefaultOidcIdTokenValidator implements OidcIdTokenValidator
{
    public function __construct(
        private OidcJwksFetcher $jwks,
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    public function validate(IdentityProvider $identityProvider, AuthAttempt $attempt, string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw OidcIdTokenValidationFailed::make('malformed jwt');
        }

        $header = $this->jsonObject($this->base64UrlDecode($parts[0]));
        $payload = $this->jsonObject($this->base64UrlDecode($parts[1]));
        $signature = $this->base64UrlDecodeBinary($parts[2]);

        $alg = $header['alg'] ?? null;
        if ($alg !== 'RS256') {
            throw OidcIdTokenValidationFailed::make('unsupported alg');
        }

        $kid = $header['kid'] ?? null;
        $kid = is_string($kid) && $kid !== '' ? $kid : null;

        $keys = $this->jwks->fetchKeys($identityProvider);

        $signingInput = $parts[0] . '.' . $parts[1];

        $verified = $this->verifyAgainstJwks($keys, $kid, $signingInput, $signature);

        if (!$verified) {
            throw OidcIdTokenValidationFailed::make('signature verification failed');
        }

        $this->validateClaims($identityProvider, $attempt, $payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function jsonObject(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw OidcIdTokenValidationFailed::make('invalid json');
        }

        return array_filter($decoded, static function ($k) {
            return is_string($k) && $k !== '';
        }, ARRAY_FILTER_USE_KEY);
    }

    private function base64UrlDecode(string $value): string
    {
        return $this->base64UrlDecodeBinary($value);
    }

    private function base64UrlDecodeBinary(string $value): string
    {
        $value = strtr($value, '-_', '+/');

        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($value, true);

        if (!is_string($decoded)) {
            throw OidcIdTokenValidationFailed::make('invalid base64url');
        }

        return $decoded;
    }

    /**
     * @param array<int, array<string, mixed>> $keys
     */
    private function verifyAgainstJwks(array $keys, ?string $kid, string $signingInput, string $signature): bool
    {
        $candidates = $this->filterKeys($keys, $kid);

        foreach ($candidates as $jwk) {
            $pem = $this->rsaJwkToPem($jwk);

            if ($pem === null) {
                continue;
            }

            $ok = openssl_verify($signingInput, $signature, $pem, OPENSSL_ALGO_SHA256);

            if ($ok === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $keys
     * @return array<int, array<string, mixed>>
     */
    private function filterKeys(array $keys, ?string $kid): array
    {
        $usable = [];

        foreach ($keys as $k) {
            $use = $k['use'] ?? null;
            $use = is_string($use) && $use !== '' ? $use : null;

            if ($use !== null && $use !== 'sig') {
                continue;
            }

            $kty = $k['kty'] ?? null;
            if ($kty !== 'RSA') {
                continue;
            }

            $usable[] = $k;
        }

        if ($kid === null) {
            return $usable;
        }

        $filtered = [];

        foreach ($usable as $k) {
            $keyKid = $k['kid'] ?? null;

            if (is_string($keyKid) && $keyKid === $kid) {
                $filtered[] = $k;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function rsaJwkToPem(array $jwk): ?string
    {
        $n = $jwk['n'] ?? null;
        $e = $jwk['e'] ?? null;

        if (!is_string($n) || $n === '' || !is_string($e) || $e === '') {
            return null;
        }

        $modulus = $this->base64UrlDecodeBinary($n);
        $exponent = $this->base64UrlDecodeBinary($e);

        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($modulus) .
          $this->asn1Integer($exponent),
        );

        $oidRsaEncryption = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01";
        $algId = $this->asn1Sequence($oidRsaEncryption . "\x05\x00");
        $bitString = "\x03" . $this->asn1Length(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;

        $spki = $this->asn1Sequence($algId . $bitString);

        return "-----BEGIN PUBLIC KEY-----\n" .
          chunk_split(base64_encode($spki), 64, "\n") .
          "-----END PUBLIC KEY-----\n";
    }

    private function asn1Sequence(string $bytes): string
    {
        return "\x30" . $this->asn1Length(strlen($bytes)) . $bytes;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 0x80) {
            return $this->chrByte($length);
        }

        $out = '';

        while ($length > 0) {
            $out = $this->chrByte($length & 0xFF) . $out;
            $length >>= 8;
        }

        return $this->chrByte(0x80 | strlen($out)) . $out;
    }

    private function asn1Integer(string $bytes): string
    {
        if ($bytes === '') {
            return "\x02\x01\x00";
        }

        if ((ord($bytes[0]) & 0x80) === 0x80) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->asn1Length(strlen($bytes)) . $bytes;
    }

    private function chrByte(int $value): string
    {
        $byte = $value & 0xFF;

        return chr($byte);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function validateClaims(IdentityProvider $identityProvider, AuthAttempt $attempt, array $claims): void
    {
        $issuerExpected = $this->expectedIssuer($identityProvider);

        $iss = $claims['iss'] ?? null;
        if (!is_string($iss) || $iss === '' || rtrim($iss, '/') !== $issuerExpected) {
            throw OidcIdTokenValidationFailed::make('iss mismatch');
        }

        $clientId = $this->requiredStringConfig($identityProvider, 'client_id');

        $aud = $claims['aud'] ?? null;
        $audiences = $this->audiences($aud);

        if (!in_array($clientId, $audiences, true)) {
            throw OidcIdTokenValidationFailed::make('aud mismatch');
        }

        if (count($audiences) > 1) {
            $azp = $claims['azp'] ?? null;

            if (!is_string($azp) || $azp === '' || $azp !== $clientId) {
                throw OidcIdTokenValidationFailed::make('azp mismatch');
            }
        }

        $exp = $claims['exp'] ?? null;
        if (!is_int($exp) && !is_float($exp)) {
            throw OidcIdTokenValidationFailed::make('exp missing');
        }

        $now = time();
        $skew = $this->clockSkewSeconds();

        if (($now - $skew) >= (int)$exp) {
            throw OidcIdTokenValidationFailed::make('token expired');
        }

        $nbf = $claims['nbf'] ?? null;
        if ((is_int($nbf) || is_float($nbf)) && ($now + $skew) < (int)$nbf) {
            throw OidcIdTokenValidationFailed::make('token not yet valid');
        }

        $iat = $claims['iat'] ?? null;
        if (is_int($iat) || is_float($iat)) {
            if (($now + $skew) < (int)$iat) {
                throw OidcIdTokenValidationFailed::make('iat is in the future');
            }

            $maxAge = $this->maxAgeSeconds();
            if ($maxAge !== null && ($now - $skew - (int)$iat) > $maxAge) {
                throw OidcIdTokenValidationFailed::make('token too old');
            }
        }

        $nonce = $claims['nonce'] ?? null;
        if (
          !is_string($nonce) ||
          $nonce === '' ||
          !is_string($attempt->nonce) ||
          $attempt->nonce === '' ||
          $nonce !== $attempt->nonce
        ) {
            throw OidcIdTokenValidationFailed::make('nonce mismatch');
        }
    }

    /**
     * @return array<int, string>
     */
    private function audiences(mixed $aud): array
    {
        if (is_string($aud) && $aud !== '') {
            return [$aud];
        }

        if (!is_array($aud)) {
            return [];
        }

        $out = [];

        foreach ($aud as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }

    private function expectedIssuer(IdentityProvider $identityProvider): string
    {
        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $issuer = $config['issuer'] ?? null;

        if (!is_string($issuer) || $issuer === '') {
            throw OidcIdTokenValidationFailed::make('missing issuer config');
        }

        return rtrim($issuer, '/');
    }

    private function requiredStringConfig(IdentityProvider $identityProvider, string $key): string
    {
        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $value = $config[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw OidcIdTokenValidationFailed::make("missing config {$key}");
        }

        return $value;
    }

    private function clockSkewSeconds(): int
    {
        return $this->nonNegativeIntConfig('sso.oidc.id_token.clock_skew_seconds', 60);
    }

    private function maxAgeSeconds(): ?int
    {
        $value = config('sso.oidc.id_token.max_age_seconds');

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        return null;
    }

    private function nonNegativeIntConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int)$value;
        }

        return $default;
    }
}
