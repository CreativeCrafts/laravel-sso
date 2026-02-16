<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('creates all required sso tables', function () {
    $tables = [
      'sso_tenants',
      'sso_identity_providers',
      'sso_connections',
      'sso_auth_attempts',
      'sso_external_identities',
      'sso_audit_logs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))
          ->toBeTrue();
    }
});
