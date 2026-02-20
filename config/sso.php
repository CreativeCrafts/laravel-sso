<?php

declare(strict_types=1);

return [
  'enabled' => env('SSO_ENABLED', true),

  'routes' => [
    'enabled' => env('SSO_ROUTES_ENABLED', true),
    'prefix' => env('SSO_ROUTE_PREFIX', 'sso'),
    'middleware' => ['web'],
  ],

  'ui' => [
    'enabled' => env('SSO_UI_ENABLED', false),
    'prefix' => env('SSO_UI_PREFIX', 'admin/sso'),
    'middleware' => ['web', 'auth'],
    'gate' => env('SSO_UI_GATE', 'manageSso'),
  ],
  'tenancy' => [
    'route_param' => 'tenant',
    'default_tenant_ulid' => env('SSO_DEFAULT_TENANT_ULID'),
  ],
  'drivers' => [
      // 'oidc' => \CreativeCrafts\LaravelSso\Drivers\OidcDriver::class,
      // 'saml' => \CreativeCrafts\LaravelSso\Drivers\SamlDriver::class,
  ],
  'attempts' => [
    'ttl_seconds' => (int)env('SSO_ATTEMPT_TTL_SECONDS', 600),
    'state_length' => (int)env('SSO_STATE_LENGTH', 64),
    'nonce_length' => (int)env('SSO_NONCE_LENGTH', 64),
    'code_verifier_length' => (int)env('SSO_CODE_VERIFIER_LENGTH', 96),
  ],
  'provisioning' => [
    'email_column' => env('SSO_USER_EMAIL_COLUMN', 'email'),
    'name_column' => env('SSO_USER_NAME_COLUMN', 'name'),
  ],
  'oidc' => [
    'discovery' => [
      'enabled_default' => env('SSO_OIDC_DISCOVERY_ENABLED', true),
      'cache_ttl_seconds' => env('SSO_OIDC_DISCOVERY_CACHE_TTL', 3600),
      'http_timeout_seconds' => env('SSO_OIDC_DISCOVERY_HTTP_TIMEOUT', 10),
    ],
  ],
];
