<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Drivers;

use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcClaimsNormalizer;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcIdTokenValidator;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Exceptions\OidcAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackCodeMissing;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackErrorResponse;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcTokenExchangeFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcUserinfoFailed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Protocol\Oidc\OidcPkce;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Throwable;

final readonly class OidcDriver implements SsoDriver
{
    public function __construct(
        private OidcEndpointResolver $endpoints,
        private OidcIdTokenValidator $idTokens,
        private OidcClaimsNormalizer $claimsNormalizer,
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

            if ($idToken === null) {
                throw OidcIdTokenValidationFailed::make('missing id_token');
            }

            $rawClaims = $this->idTokens->validate($identityProvider, $attempt, $idToken);

            $userinfoEnabled = $this->userinfoEnabled($identityProvider);
            if ($userinfoEnabled && $accessToken !== null && $ep->userinfoEndpoint !== null) {
                $userinfoClaims = $this->fetchUserinfo($ep->userinfoEndpoint, $accessToken, $timeout);
                $rawClaims = array_merge($rawClaims, $userinfoClaims);
            }

            $canonical = $this->claimsNormalizer->normalize($rawClaims);

            return new DriverCallbackResult(
                authenticated: true,
                canonicalClaims: $canonical,
                subject: $canonical->subject,
                email: $canonical->email,
                displayName: $canonical->displayName,
                claims: $canonical->toArray(),
                context: [
                'token_endpoint' => $ep->tokenEndpoint,
                'userinfo_used' => $userinfoEnabled && $ep->userinfoEndpoint !== null && $accessToken !== null,
                'raw_claims' => $rawClaims,
              ],
                error: null,
            );
        } catch (OidcTokenExchangeFailed|OidcUserinfoFailed|OidcCallbackErrorResponse|OidcCallbackCodeMissing|OidcIdTokenValidationFailed $e) {
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

            return array_filter($json, static function ($k) {
                return is_string($k) && $k !== '';
            }, ARRAY_FILTER_USE_KEY);
        } catch (OidcUserinfoFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            throw OidcUserinfoFailed::make('unexpected error', $e);
        }
    }
}
