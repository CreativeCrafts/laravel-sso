<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Drivers\OidcDriver;
use CreativeCrafts\LaravelSso\Drivers\SamlDriver;

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

    'guards' => [
        // If set, overrides auth.defaults.guard when connection.guard is null.
        'default' => env('SSO_DEFAULT_GUARD'),

        // Optional allowlist. If empty/null, all configured auth guards are permitted.
        // Example: SSO_ALLOWED_GUARDS=web,admin
        'allowed' => array_values(
            array_filter(
                array_map(
                    static fn (string $v): string => trim($v),
                    explode(',', (string) env('SSO_ALLOWED_GUARDS', '')),
                ),
            ),
        ),
    ],

    'drivers' => [
        'oidc' => OidcDriver::class,
        'saml' => SamlDriver::class,
    ],

    'attempts' => [
        'ttl_seconds' => (int) env('SSO_ATTEMPT_TTL_SECONDS', 600),
        'state_length' => (int) env('SSO_STATE_LENGTH', 64),
        'nonce_length' => (int) env('SSO_NONCE_LENGTH', 64),
        'code_verifier_length' => (int) env('SSO_CODE_VERIFIER_LENGTH', 96),
    ],

    'provisioning' => [
        'email_column' => env('SSO_USER_EMAIL_COLUMN', 'email'),
        'name_column' => env('SSO_USER_NAME_COLUMN', 'name'),

        // Deny by default. Set to true to allow provisioning package-wide when a
        // connection does not declare an explicit override in settings.
        'enabled_by_default' => env('SSO_PROVISIONING_ENABLED', false),

        // Connection-level override key stored in sso_connections.settings.
        // Example: ['allow_provisioning' => true]
        'connection_setting_key' => env('SSO_PROVISIONING_CONNECTION_SETTING_KEY', 'allow_provisioning'),
    ],

    'linking' => [
        // Deny by default. Set to true to allow linking package-wide when a
        // connection does not declare an explicit override in settings.
        'enabled_by_default' => env('SSO_LINKING_ENABLED', false),

        // Connection-level override key stored in sso_connections.settings.
        // Example: ['allow_identity_linking' => true]
        'connection_setting_key' => env('SSO_LINKING_CONNECTION_SETTING_KEY', 'allow_identity_linking'),
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
        'clock_skew_seconds' => (int) env('SSO_SAML_CLOCK_SKEW_SECONDS', 60),

        'require_destination' => env('SSO_SAML_REQUIRE_DESTINATION', true),
        'require_audience' => env('SSO_SAML_REQUIRE_AUDIENCE', true),
        'require_recipient' => env('SSO_SAML_REQUIRE_RECIPIENT', true),

        'attribute_mapping' => [
            'email' => [
                'email',
                'mail',
                'EmailAddress',
                'upn',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/upn',
            ],
            'display_name' => [
                'name',
                'displayName',
                'cn',
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
            ],
            'given_name' => [
                'givenName',
                'firstName',
            ],
            'surname' => [
                'sn',
                'surname',
                'lastName',
            ],
            'groups' => [
                'groups',
                'memberOf',
                'roles',
                'http://schemas.microsoft.com/ws/2008/06/identity/claims/role',
            ],
        ],

        'sp' => [
            // Optional override. If null, we default entityID to the metadata URL.
            'entity_id' => env('SSO_SAML_SP_ENTITY_ID'),

            // Binding for ACS endpoint in generated metadata.
            'acs_binding' => env('SSO_SAML_SP_ACS_BINDING', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'),
        ],
    ],
];
