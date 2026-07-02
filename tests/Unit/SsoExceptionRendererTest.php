<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptExpired;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptNotFound;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptValidationInProgress;
use CreativeCrafts\LaravelSso\Exceptions\CallbackStateMissing;
use CreativeCrafts\LaravelSso\Exceptions\EmailVerificationRequired;
use CreativeCrafts\LaravelSso\Exceptions\GuardSelectionFailed;
use CreativeCrafts\LaravelSso\Exceptions\IdentityLinkDenied;
use CreativeCrafts\LaravelSso\Exceptions\InvalidAuthAttemptBinding;
use CreativeCrafts\LaravelSso\Exceptions\MissingExternalSubject;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackCodeMissing;
use CreativeCrafts\LaravelSso\Exceptions\OidcDiscoveryFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcTokenExchangeFailed;
use CreativeCrafts\LaravelSso\Exceptions\ProvisioningDenied;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionReplayDetected;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SsoResourceDisabled;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use CreativeCrafts\LaravelSso\Exceptions\UnsupportedSsoProtocol;
use CreativeCrafts\LaravelSso\Exceptions\UserEmailAlreadyExists;
use CreativeCrafts\LaravelSso\Http\SsoExceptionRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('maps sso exceptions to http statuses on public sso routes', function (Throwable $exception, int $expectedStatus): void {
    $request = Request::create('/sso/tenant/connection/callback', 'GET');

    $response = (new SsoExceptionRenderer())->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response?->getStatusCode())->toBe($expectedStatus);
})->with([
    'expired attempt' => [AuthAttemptExpired::forState('state'), Response::HTTP_GONE],
    'already consumed' => [AuthAttemptAlreadyConsumed::forState('state'), Response::HTTP_CONFLICT],
    'validation in progress' => [AuthAttemptValidationInProgress::forState('state'), Response::HTTP_CONFLICT],
    'invalid binding' => [InvalidAuthAttemptBinding::connectionMismatch(1, 2), Response::HTTP_CONFLICT],
    'email already exists' => [UserEmailAlreadyExists::forEmail('a@b.test'), Response::HTTP_CONFLICT],
    'not found attempt' => [AuthAttemptNotFound::forState('state'), Response::HTTP_NOT_FOUND],
    'tenant scoped missing' => [TenantScopedRecordNotFound::for('connection', 'ulid'), Response::HTTP_NOT_FOUND],
    'replay' => [SamlAssertionReplayDetected::make(), Response::HTTP_CONFLICT],
    'provisioning denied' => [ProvisioningDenied::make(), Response::HTTP_FORBIDDEN],
    'link denied' => [IdentityLinkDenied::make(), Response::HTTP_FORBIDDEN],
    'email verification' => [EmailVerificationRequired::forLinking(), Response::HTTP_FORBIDDEN],
    'disabled resource' => [SsoResourceDisabled::for('connection', 1), Response::HTTP_NOT_FOUND],
    'missing state' => [CallbackStateMissing::make(), Response::HTTP_BAD_REQUEST],
    'missing external subject' => [MissingExternalSubject::make(), Response::HTTP_BAD_REQUEST],
    'oidc code missing' => [OidcCallbackCodeMissing::make(), Response::HTTP_BAD_REQUEST],
    'unsupported protocol' => [UnsupportedSsoProtocol::for('ldap'), Response::HTTP_BAD_REQUEST],
    'oidc token invalid' => [OidcIdTokenValidationFailed::make('invalid'), Response::HTTP_UNAUTHORIZED],
    'saml signature invalid' => [SamlSignatureInvalid::make(), Response::HTTP_UNAUTHORIZED],
    'oidc token exchange failed' => [OidcTokenExchangeFailed::make('bad'), Response::HTTP_UNAUTHORIZED],
    'unsafe idp url' => [UnsafeIdpUrl::forField('issuer', 'private'), Response::HTTP_BAD_GATEWAY],
    'oidc discovery failed' => [OidcDiscoveryFailed::forUrl('https://idp.test'), Response::HTTP_BAD_GATEWAY],
    'guard selection failed' => [GuardSelectionFailed::notConfigured('web'), Response::HTTP_INTERNAL_SERVER_ERROR],
]);

it('maps sso exceptions on admin api routes', function (): void {
    $request = Request::create('/admin/sso/tenants', 'GET');

    $response = (new SsoExceptionRenderer())->render(TenantNotFound::forUlid('01JULID0000000000000000'), $request);

    expect($response?->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
});

it('returns null for non sso routes', function (): void {
    $request = Request::create('/dashboard', 'GET');

    $response = (new SsoExceptionRenderer())->render(ProvisioningDenied::make(), $request);

    expect($response)->toBeNull();
});

it('returns null for unmapped sso exceptions', function (): void {
    $request = Request::create('/sso/tenant/connection/callback', 'GET');

    $response = (new SsoExceptionRenderer())->render(new RuntimeException('unexpected'), $request);

    expect($response)->toBeNull();
});
