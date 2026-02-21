<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Drivers;

use CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Oidc\OidcEndpointResolver;
use CreativeCrafts\LaravelSso\Core\Dto\DriverCallbackResult;
use CreativeCrafts\LaravelSso\Core\Dto\DriverStartResult;
use CreativeCrafts\LaravelSso\Exceptions\OidcAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Models\AuthAttempt;
use CreativeCrafts\LaravelSso\Models\Connection;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Protocol\Oidc\OidcPkce;
use Illuminate\Http\Request;

final readonly class OidcDriver implements SsoDriver
{
    public function __construct(
        private OidcEndpointResolver $endpoints,
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
            context: [
            'pkce' => 'S256',
          ],
        );
    }

    public function handleCallback(Request $request, Tenant $tenant, Connection $connection, AuthAttempt $attempt): DriverCallbackResult
    {
        return new DriverCallbackResult(
            authenticated: false,
            error: 'oidc_callback_not_implemented',
        );
    }
}
