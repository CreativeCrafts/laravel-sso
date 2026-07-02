<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http;

use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptAlreadyConsumed;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptExpired;
use CreativeCrafts\LaravelSso\Exceptions\AuthAttemptValidationInProgress;
use CreativeCrafts\LaravelSso\Exceptions\EmailVerificationRequired;
use CreativeCrafts\LaravelSso\Exceptions\IdentityLinkDenied;
use CreativeCrafts\LaravelSso\Exceptions\ProvisioningDenied;
use CreativeCrafts\LaravelSso\Exceptions\SamlAssertionReplayDetected;
use CreativeCrafts\LaravelSso\Exceptions\SsoResourceDisabled;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
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
            SamlAssertionReplayDetected::class => Response::HTTP_CONFLICT,
            AuthAttemptExpired::class => Response::HTTP_GONE,
            IdentityLinkDenied::class,
            ProvisioningDenied::class,
            EmailVerificationRequired::class => Response::HTTP_FORBIDDEN,
            SsoResourceDisabled::class,
            TenantScopedRecordNotFound::class => Response::HTTP_NOT_FOUND,
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

        return $request->is($prefix . '/*');
    }
}
