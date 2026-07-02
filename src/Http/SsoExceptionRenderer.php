<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http;

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
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionConditionsInvalid;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionReplayDetected;
use CreativeCrafts\LaravelSso\Exceptions\SamlAcsRequestInvalid;
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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SsoExceptionRenderer
{
    public function render(Throwable $exception, Request $request): ?Response
    {
        if (!$this->isSsoRequest($request)) {
            return null;
        }

        $status = match ($exception::class) {
            AuthAttemptAlreadyConsumed::class,
            AuthAttemptValidationInProgress::class,
            InvalidAuthAttemptBinding::class,
            SamlAssertionReplayDetected::class,
            UserEmailAlreadyExists::class => Response::HTTP_CONFLICT,
            AuthAttemptExpired::class => Response::HTTP_GONE,
            AuthAttemptNotFound::class,
            TenantNotFound::class,
            TenantScopedRecordNotFound::class,
            SsoResourceDisabled::class => Response::HTTP_NOT_FOUND,
            IdentityLinkDenied::class,
            ProvisioningDenied::class,
            EmailVerificationRequired::class => Response::HTTP_FORBIDDEN,
            CallbackStateMissing::class,
            MissingExternalSubject::class,
            OidcCallbackCodeMissing::class,
            SamlAcsRequestInvalid::class,
            SamlResponseStatusInvalid::class,
            SamlSignatureMissing::class,
            UnsupportedSsoProtocol::class => Response::HTTP_BAD_REQUEST,
            OidcCallbackErrorResponse::class,
            OidcIdTokenValidationFailed::class,
            OidcTokenExchangeFailed::class,
            SamlAssertionConditionsInvalid::class,
            SamlClaimsNormalizationFailed::class,
            SamlSignatureInvalid::class => Response::HTTP_UNAUTHORIZED,
            OidcAuthorizationRequestFailed::class,
            OidcDiscoveryFailed::class,
            OidcEndpointResolutionFailed::class,
            OidcJwksFetchFailed::class,
            OidcUserinfoFailed::class,
            SamlAuthorizationRequestFailed::class,
            SamlMetadataParseFailed::class,
            TenantResolutionFailed::class,
            UnsafeIdpUrl::class => Response::HTTP_BAD_GATEWAY,
            GuardSelectionFailed::class => Response::HTTP_INTERNAL_SERVER_ERROR,
            default => null,
        };

        if ($status === null) {
            return null;
        }

        return response('', $status);
    }

    private function isSsoRequest(Request $request): bool
    {
        $prefix = config('sso.routes.prefix', 'sso');
        $prefix = is_string($prefix) && $prefix !== '' ? trim($prefix, '/') : 'sso';

        if ($request->is($prefix . '/*')) {
            return true;
        }

        $uiPrefix = config('sso.ui.prefix', 'admin/sso');
        $uiPrefix = is_string($uiPrefix) && $uiPrefix !== '' ? trim($uiPrefix, '/') : 'admin/sso';

        return $request->is($uiPrefix . '/*');
    }
}
