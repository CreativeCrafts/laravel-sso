<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Drivers\OidcDriver;
use CreativeCrafts\LaravelSso\Drivers\SamlDriver;

/**
 * @return array{
 *     enabled: bool,
 *     routes: array{enabled: bool, prefix: string, middleware: array<int, string>},
 *     ui: array{enabled: bool, prefix: string, middleware: array<int, string>, gate: string},
 *     tenancy: array{
 *         route_param: string,
 *         default_tenant_ulid?: string|null,
 *         throw_if_missing: bool,
 *         header: array{enabled: bool, name: string},
 *         host: array{enabled: bool, mode: string, base_domain?: string|null}
 *     },
 *     guards: array{default?: string|null, allowed: array<int, string>},
 *     drivers: array<string, class-string>,
 *     attempts: array{ttl_seconds: int, state_length: int, nonce_length: int, code_verifier_length: int},
 *     provisioning: array{email_column: string, name_column: string, enabled_by_default: bool, connection_setting_key: string},
 *     throttling: array<string, array{enabled: bool, max_attempts: int, decay_minutes: int}>,
 *     linking: array{enabled_by_default: bool, connection_setting_key: string},
 *     audit: array<string, int|string|bool>,
 *     oidc: array<string, mixed>,
 *     saml: array<string, mixed>
 * }
 */

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

    'throttling' => [
        'redirect' => [
            'enabled' => env('SSO_THROTTLE_REDIRECT_ENABLED', true),
            'max_attempts' => (int) env('SSO_THROTTLE_REDIRECT_MAX_ATTEMPTS', 60),
            'decay_minutes' => (int) env('SSO_THROTTLE_REDIRECT_DECAY_MINUTES', 1),
        ],
        'callback' => [
            'enabled' => env('SSO_THROTTLE_CALLBACK_ENABLED', true),
            'max_attempts' => (int) env('SSO_THROTTLE_CALLBACK_MAX_ATTEMPTS', 30),
            'decay_minutes' => (int) env('SSO_THROTTLE_CALLBACK_DECAY_MINUTES', 1),
        ],
        'acs' => [
            'enabled' => env('SSO_THROTTLE_ACS_ENABLED', true),
            'max_attempts' => (int) env('SSO_THROTTLE_ACS_MAX_ATTEMPTS', 30),
            'decay_minutes' => (int) env('SSO_THROTTLE_ACS_DECAY_MINUTES', 1),
        ],
    ],

    'linking' => [
        // Deny by default. Set to true to allow linking package-wide when a
        // connection does not declare an explicit override in settings.
        'enabled_by_default' => env('SSO_LINKING_ENABLED', false),

        // Connection-level override key stored in sso_connections.settings.
        // Example: ['allow_identity_linking' => true]
        'connection_setting_key' => env('SSO_LINKING_CONNECTION_SETTING_KEY', 'allow_identity_linking'),
    ],

    'audit' => [
        // Safe by default. When enabled, stores additional redacted summaries
        // for callback claims and driver context. Raw secrets, tokens, and SAML
        // payloads must still never be persisted.
        'extended_context' => env('SSO_AUDIT_EXTENDED_CONTEXT', false),

        // One-way hash used for stable subject correlation without persisting the
        // raw subject value in audit records.
        'subject_hash_algo' => env('SSO_AUDIT_SUBJECT_HASH_ALGO', 'sha256'),

        // Max visible prefix retained for bounded subject hints.
        'subject_hint_length' => (int) env('SSO_AUDIT_SUBJECT_HINT_LENGTH', 12),

        // Max stored string length inside redacted audit context.
        'string_value_max_length' => (int) env('SSO_AUDIT_STRING_VALUE_MAX_LENGTH', 80),

        // Max number of claim keys retained in summarized audit context.
        'max_claim_keys' => (int) env('SSO_AUDIT_MAX_CLAIM_KEYS', 20),

        // Max number of items retained when sanitizing arrays for extended context.
        'max_array_items' => (int) env('SSO_AUDIT_MAX_ARRAY_ITEMS', 20),
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
