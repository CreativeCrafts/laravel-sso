<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;

interface SamlClaimsNormalizer
{
    public function normalize(SamlSignedXml $signed): Claims;
}
