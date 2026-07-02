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
use CreativeCrafts\LaravelSso\Exceptions\OidcAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackCodeMissing;
use CreativeCrafts\LaravelSso\Exceptions\OidcCallbackErrorResponse;
use CreativeCrafts\LaravelSso\Exceptions\OidcDiscoveryFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcEndpointResolutionFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcIdTokenValidationFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcJwksFetchFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcTokenExchangeFailed;
use CreativeCrafts\LaravelSso\Exceptions\OidcUserinfoFailed;
use CreativeCrafts\LaravelSso\Exceptions\ProvisioningDenied;
use CreativeCrafts\LaravelSso\Exceptions\SamlAcsRequestInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionConditionsInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionReplayDetected;
use CreativeCrafts\LaravelSso\Exceptions\SamlAuthorizationRequestFailed;
use CreativeCrafts\LaravelSso\Exceptions\SamlClaimsNormalizationFailed;
use CreativeCrafts\LaravelSso\Exceptions\SamlMetadataParseFailed;
use CreativeCrafts\LaravelSso\Exceptions\SamlResponseStatusInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlSignatureMissing;
use CreativeCrafts\LaravelSso\Exceptions\SsoResourceDisabled;
use CreativeCrafts\LaravelSso\Exceptions\TenantNotFound;
use CreativeCrafts\LaravelSso\Exceptions\TenantResolutionFailed;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Exceptions\UnsafeIdpUrl;
use CreativeCrafts\LaravelSso\Exceptions\UnsupportedSsoProtocol;
use CreativeCrafts\LaravelSso\Exceptions\UserEmailAlreadyExists;

it('instantiates all public sso exception factories', function (): void {
    $exceptions = [
        AuthAttemptAlreadyConsumed::forState('state'),
        AuthAttemptExpired::forState('state'),
        AuthAttemptNotFound::forState('state'),
        AuthAttemptValidationInProgress::forState('state'),
        CallbackStateMissing::make(),
        EmailVerificationRequired::forLinking(),
        EmailVerificationRequired::forProvisioning(),
        GuardSelectionFailed::notConfigured('web'),
        GuardSelectionFailed::notAllowed('api'),
        IdentityLinkDenied::make(),
        InvalidAuthAttemptBinding::connectionMismatch(1, 2),
        InvalidAuthAttemptBinding::identityProviderMismatch(1, 2),
        MissingExternalSubject::make(),
        OidcAuthorizationRequestFailed::missingConfig('client_id'),
        OidcAuthorizationRequestFailed::missingAttemptField('nonce'),
        OidcCallbackCodeMissing::make(),
        OidcCallbackErrorResponse::fromProvider('access_denied', 'User cancelled'),
        OidcDiscoveryFailed::forUrl('https://idp.test/.well-known/openid-configuration'),
        OidcEndpointResolutionFailed::missingConfig(),
        OidcIdTokenValidationFailed::make('bad signature'),
        OidcJwksFetchFailed::forUrl('https://idp.test/jwks'),
        OidcTokenExchangeFailed::make('invalid_grant'),
        OidcUserinfoFailed::make('401'),
        ProvisioningDenied::make(),
        SamlAcsRequestInvalid::missingResponse(),
        SamlAcsRequestInvalid::invalidBase64(),
        SamlAcsRequestInvalid::invalidXml(),
        SamlAcsRequestInvalid::missingRequestId(),
        SamlAcsRequestInvalid::correlationMissing(),
        SamlAcsRequestInvalid::correlationMismatch(),
        SamlAssertionConditionsInvalid::audienceMismatch(),
        SamlAssertionConditionsInvalid::recipientMismatch(),
        SamlAssertionConditionsInvalid::destinationMismatch(),
        SamlAssertionConditionsInvalid::notYetValid(),
        SamlAssertionConditionsInvalid::expired(),
        SamlAssertionConditionsInvalid::invalidTimestamp(),
        SamlAssertionReplayDetected::make(),
        SamlAuthorizationRequestFailed::missingConfig('saml_sso_url'),
        SamlAuthorizationRequestFailed::missingAttemptField('request_id'),
        SamlAuthorizationRequestFailed::missingIdentityProvider(),
        SamlAuthorizationRequestFailed::compressionFailed(),
        SamlAuthorizationRequestFailed::signingFailed(),
        SamlAuthorizationRequestFailed::signingKeysMissing(),
        SamlClaimsNormalizationFailed::missingNameId(),
        SamlMetadataParseFailed::invalidXml(),
        SamlMetadataParseFailed::missingEntityId(),
        SamlMetadataParseFailed::missingIdpDescriptor(),
        SamlMetadataParseFailed::missingSsoService(),
        SamlMetadataParseFailed::missingSigningCertificate(),
        SamlResponseStatusInvalid::make('Responder'),
        SamlResponseStatusInvalid::make(),
        SamlSignatureInvalid::make(),
        SamlSignatureMissing::make(),
        SsoResourceDisabled::for('connection', 1),
        TenantNotFound::forUlid('01JULID0000000000000000'),
        TenantResolutionFailed::unableToResolve(),
        TenantResolutionFailed::misconfigured('missing header'),
        TenantScopedRecordNotFound::for('connection', 'ulid'),
        UnsafeIdpUrl::forField('issuer', 'private network'),
        UnsupportedSsoProtocol::for('ldap'),
        UserEmailAlreadyExists::forEmail('user@example.test'),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getMessage())->not->toBe('');
    }

    expect($exceptions)->toHaveCount(61);
});
