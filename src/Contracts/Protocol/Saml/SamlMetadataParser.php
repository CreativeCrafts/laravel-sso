<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlIdpMetadata;

interface SamlMetadataParser
{
    public function parse(string $xml): SamlIdpMetadata;
}
