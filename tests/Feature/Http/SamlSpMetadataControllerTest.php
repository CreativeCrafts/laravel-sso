<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Tests\TestCase;

uses(TestCase::class);

it('returns SP metadata XML with correct content-type', function () {
    $this->withoutExceptionHandling();

    $metadataUrl = route('sso.saml.metadata', ['tenant' => 't1', 'connection' => '1'], true);
    $acsUrl = route('sso.saml.acs', ['tenant' => 't1', 'connection' => '1'], true);

    $resp = $this->get($metadataUrl);

    $resp->assertOk();
    $resp->assertHeader('Content-Type', 'application/samlmetadata+xml; charset=UTF-8');

    $resp->assertSee('EntityDescriptor', escape: false);
    $resp->assertSee('SPSSODescriptor', escape: false);
    $resp->assertSee('AssertionConsumerService', escape: false);

    $resp->assertSee('entityID="' . $metadataUrl . '"', escape: false);
    $resp->assertSee('Location="' . $acsUrl . '"', escape: false);
});
