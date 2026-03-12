<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Protocol\Saml\SpMetadataGenerator;

it('generates SP metadata containing entityID and ACS Location', function () {
    $tenant = 'tenant-ulid-1';
    $connection = '1';

    $xml = app(SpMetadataGenerator::class)->generate($tenant, $connection);

    expect($xml)
        ->toContain('EntityDescriptor')
        ->and($xml)->toContain('SPSSODescriptor')
        ->and($xml)->toContain('AssertionConsumerService');

    $acsUrl = route('sso.saml.acs', ['tenant' => $tenant, 'connection' => $connection], true);
    $metadataUrl = route('sso.saml.metadata', ['tenant' => $tenant, 'connection' => $connection], true);

    expect($xml)
        ->toContain('Location="' . $acsUrl . '"')
        ->and($xml)->toContain('entityID="' . $metadataUrl . '"');
});
