<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Drivers;

use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Exceptions\OidcAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackCodeMissing;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackErrorResponse;
use CreativeCrafts\LaravelSso\Exceptions\OidcTokenExchangeFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcUserinfoFailed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Protocol\Oidc\OidcPkce;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use JsonException;
use Throwable;

final readonly class OidcDriver implements SsoDriver
{
    public function __construct(
        private OidcEndpointResolver $endpoints,
        private HttpFactory $http,
    ) {
    }

    public function protocol(): string
    {
        return 'oidc';
    }

    public function start(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverStartResult
    {
        $identityProvider = $connection->identityProvider;

        if (!$identityProvider instanceof IdentityProvider) {
            throw OidcAuthorizationRequestFailed::missingConfig('identity_provider');
        }

        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $clientId = $config['client_id'] ?? null;
        if (!is_string($clientId) || $clientId === '') {
            throw OidcAuthorizationRequestFailed::missingConfig('client_id');
        }

        $redirectUri = $config['redirect_uri'] ?? null;
        if (!is_string($redirectUri) || $redirectUri === '') {
            throw OidcAuthorizationRequestFailed::missingConfig('redirect_uri');
        }

        $scope = $config['scope'] ?? 'openid email profile';
        if (!is_string($scope) || $scope === '') {
            $scope = 'openid email profile';
        }

        $responseType = $config['response_type'] ?? 'code';
        if (!is_string($responseType) || $responseType === '') {
            $responseType = 'code';
        }

        if ($attempt->state === '') {
            throw OidcAuthorizationRequestFailed::missingAttemptField('state');
        }

        if (!is_string($attempt->nonce) || $attempt->nonce === '') {
            throw OidcAuthorizationRequestFailed::missingAttemptField('nonce');
        }

        if (!is_string($attempt->code_verifier) || $attempt->code_verifier === '') {
            throw OidcAuthorizationRequestFailed::missingAttemptField('code_verifier');
        }

        $ep = $this->endpoints->resolve($identityProvider);

        $codeChallenge = OidcPkce::codeChallengeS256($attempt->code_verifier);

        $params = [
          'client_id' => $clientId,
          'redirect_uri' => $redirectUri,
          'response_type' => $responseType,
          'scope' => $scope,
          'state' => $attempt->state,
          'nonce' => $attempt->nonce,
          'code_challenge' => $codeChallenge,
          'code_challenge_method' => 'S256',
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $separator = str_contains($ep->authorizationEndpoint, '?') ? '&' : '?';

        return new DriverStartResult(
            redirectUrl: $ep->authorizationEndpoint . $separator . $query,
            context: ['pkce' => 'S256'],
        );
    }

    public function handleCallback(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverCallbackResult
    {
        $identityProvider = $connection->identityProvider;

        if (!$identityProvider instanceof IdentityProvider) {
            throw OidcTokenExchangeFailed::make('missing identity provider on connection');
        }

        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $error = $request->query('error') ?? $request->input('error');
        if (is_string($error) && $error !== '') {
            $description = $request->query('error_description') ?? $request->input('error_description');
            $description = is_string($description) && $description !== '' ? $description : null;

            throw OidcCallbackErrorResponse::fromProvider($error, $description);
        }

        $code = $request->query('code') ?? $request->input('code');
        if (!is_string($code) || $code === '') {
            throw OidcCallbackCodeMissing::make();
        }

        $clientId = $config['client_id'] ?? null;
        if (!is_string($clientId) || $clientId === '') {
            throw OidcTokenExchangeFailed::make('missing config client_id');
        }

        $redirectUri = $config['redirect_uri'] ?? null;
        if (!is_string($redirectUri) || $redirectUri === '') {
            throw OidcTokenExchangeFailed::make('missing config redirect_uri');
        }

        $clientSecret = $config['client_secret'] ?? null;
        $clientSecret = is_string($clientSecret) && $clientSecret !== '' ? $clientSecret : null;

        if (!is_string($attempt->code_verifier) || $attempt->code_verifier === '') {
            throw OidcTokenExchangeFailed::make('missing attempt code_verifier');
        }

        $ep = $this->endpoints->resolve($identityProvider);

        $timeout = $this->timeoutSeconds();

        try {
            $payload = [
              'grant_type' => 'authorization_code',
              'code' => $code,
              'redirect_uri' => $redirectUri,
              'client_id' => $clientId,
              'code_verifier' => $attempt->code_verifier,
            ];

            if ($clientSecret !== null) {
                $payload['client_secret'] = $clientSecret;
            }

            $response = $this->http
              ->timeout($timeout)
              ->asForm()
              ->acceptJson()
              ->post($ep->tokenEndpoint, $payload);

            if (!$response->successful()) {
                $json = $response->json();

                if (is_array($json)) {
                    $err = $json['error'] ?? null;
                    $desc = $json['error_description'] ?? null;

                    if (is_string($err) && $err !== '') {
                        $mapped = $this->mapTokenError($err);
                        $suffix = is_string($desc) && $desc !== '' ? ": {$desc}" : '';

                        throw OidcTokenExchangeFailed::make("{$mapped}{$suffix}");
                    }
                }

                throw OidcTokenExchangeFailed::make('non-successful HTTP response');
            }

            $json = $response->json();
            if (!is_array($json)) {
                throw OidcTokenExchangeFailed::make('token response is not JSON object');
            }

            /** @var array<string, mixed> $token */
            $token = $json;

            $accessToken = $token['access_token'] ?? null;
            $accessToken = is_string($accessToken) && $accessToken !== '' ? $accessToken : null;

            $idToken = $token['id_token'] ?? null;
            $idToken = is_string($idToken) && $idToken !== '' ? $idToken : null;

            /** @var array<string, mixed> $claims */
            $claims = [];

            if ($idToken !== null) {
                $claims = $this->decodeJwtPayloadUnverified($idToken);
            }

            $userinfoEnabled = $this->userinfoEnabled($identityProvider);
            if ($userinfoEnabled && $accessToken !== null && $ep->userinfoEndpoint !== null) {
                $userinfoClaims = $this->fetchUserinfo($ep->userinfoEndpoint, $accessToken, $timeout);
                $claims = array_merge($claims, $userinfoClaims);
            }

            $subject = $claims['sub'] ?? null;
            $subject = is_string($subject) && $subject !== '' ? $subject : null;

            $email = $claims['email'] ?? null;
            $email = is_string($email) && $email !== '' ? $email : null;

            $name = $claims['name'] ?? null;
            $name = is_string($name) && $name !== '' ? $name : null;

            return new DriverCallbackResult(
                authenticated: true,
                subject: $subject,
                email: $email,
                displayName: $name,
                claims: $claims,
                context: [
                'token_endpoint' => $ep->tokenEndpoint,
                'userinfo_used' => $userinfoEnabled && $ep->userinfoEndpoint !== null && $accessToken !== null,
                'has_id_token' => $idToken !== null,
                'has_access_token' => $accessToken !== null,
              ],
                error: null,
            );
        } catch (OidcTokenExchangeFailed|OidcUserinfoFailed|OidcCallbackErrorResponse|OidcCallbackCodeMissing $e) {
            throw $e;
        } catch (Throwable $e) {
            throw OidcTokenExchangeFailed::make('unexpected error', $e);
        }
    }

    private function timeoutSeconds(): int
    {
        $value = config('sso.oidc.callback.http_timeout_seconds', 10);

        return is_int($value) && $value > 0 ? $value : 10;
    }

    private function mapTokenError(string $providerError): string
    {
        return match ($providerError) {
            'invalid_grant' => 'oidc.invalid_grant',
            'invalid_client' => 'oidc.invalid_client',
            'invalid_request' => 'oidc.invalid_request',
            'unauthorized_client' => 'oidc.unauthorized_client',
            'unsupported_grant_type' => 'oidc.unsupported_grant_type',
            default => 'oidc.token_exchange_failed',
        };
    }

    /**
     * Decode JWT payload without signature validation (validation is next issue).
     *
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function decodeJwtPayloadUnverified(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);

        $decoded = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            return [];
        }

        return array_filter($decoded, static function ($key) {
            return is_string($key) && $key !== '';
        }, ARRAY_FILTER_USE_KEY);
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');

        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : '';
    }

    private function userinfoEnabled(IdentityProvider $identityProvider): bool
    {
        /** @var array<string, mixed> $config */
        $config = is_array($identityProvider->config) ? $identityProvider->config : [];

        $flag = $config['userinfo_enabled'] ?? null;
        if (is_bool($flag)) {
            return $flag;
        }

        $default = config('sso.oidc.userinfo.enabled_default', false);

        return is_bool($default) && $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserinfo(string $url, string $accessToken, int $timeout): array
    {
        try {
            $response = $this->http
              ->timeout($timeout)
              ->acceptJson()
              ->withToken($accessToken)
              ->get($url);

            if (!$response->successful()) {
                throw OidcUserinfoFailed::make('non-successful HTTP response');
            }

            $json = $response->json();

            if (!is_array($json)) {
                throw OidcUserinfoFailed::make('userinfo response is not JSON object');
            }

            /** @var array<string, mixed> $payload */
            $payload = $json;

            return $payload;
        } catch (OidcUserinfoFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            throw OidcUserinfoFailed::make('unexpected error', $e);
        }
    }
}
