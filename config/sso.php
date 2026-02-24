<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Drivers\OidcDriver;

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

      // When true, failing to resolve a tenant throws TenantResolutionFailed.
    'throw_if_missing' => env('SSO_TENANCY_THROW_IF_MISSING', true),

    'header' => [
      'enabled' => env('SSO_TENANCY_HEADER_ENABLED', false),
      'name' => env('SSO_TENANCY_HEADER_NAME', 'X-SSO-Tenant'),
    ],

    'host' => [
      'enabled' => env('SSO_TENANCY_HOST_ENABLED', false),

        // host: match request host against tenant metadata domain(s)
        // subdomain: parse "{tenant}.{base_domain}" and match metadata->subdomain or ulid
      'mode' => env('SSO_TENANCY_HOST_MODE', 'host'),
      'base_domain' => env('SSO_TENANCY_BASE_DOMAIN'),
    ],
  ],
  'drivers' => [
    'oidc' => OidcDriver::class,
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
    'callback' => [
      'http_timeout_seconds' => env('SSO_OIDC_CALLBACK_HTTP_TIMEOUT', 10),
    ],
    'userinfo' => [
      'enabled_default' => env('SSO_OIDC_USERINFO_ENABLED', false),
    ],
    'id_token' => [
      'clock_skew_seconds' => env('SSO_OIDC_CLOCK_SKEW_SECONDS', 60),
      'jwks_cache_ttl_seconds' => env('SSO_OIDC_JWKS_CACHE_TTL', 3600),
      'jwks_http_timeout_seconds' => env('SSO_OIDC_JWKS_HTTP_TIMEOUT', 10),
    ],
  ],
  'saml' => [
    'clock_skew_seconds' => (int)env('SSO_SAML_CLOCK_SKEW_SECONDS', 60),

    'require_destination' => env('SSO_SAML_REQUIRE_DESTINATION', true),
    'require_audience' => env('SSO_SAML_REQUIRE_AUDIENCE', true),
    'require_recipient' => env('SSO_SAML_REQUIRE_RECIPIENT', true),
    'sp' => [
        // Optional override. If null, we default entityID to the metadata URL.
      'entity_id' => env('SSO_SAML_SP_ENTITY_ID'),
        // Binding for ACS endpoint in generated metadata.
      'acs_binding' => env('SSO_SAML_SP_ACS_BINDING', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'),
    ],
  ],
];
