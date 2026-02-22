<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('GET metadata returns XML and includes ACS URL', function () {
    $tenant = 'tenant-ulid-1';
    $idp = '1';

    $response = $this->get(route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp]));

    $response->assertOk();
    $response->assertHeader('Content-Type');

    $acsUrl = route('sso.saml.acs', ['tenant' => $tenant, 'idp' => $idp], true);
    $metadataUrl = route('sso.saml.metadata', ['tenant' => $tenant, 'idp' => $idp], true);

    $response->assertSee('entityID="' . $metadataUrl . '"', false);
    $response->assertSee('AssertionConsumerService', false);
    $response->assertSee('Location="' . $acsUrl . '"', false);
});
