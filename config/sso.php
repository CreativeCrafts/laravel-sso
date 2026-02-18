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
];
