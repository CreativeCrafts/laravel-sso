<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Core\Dto\Claims;

interface SamlClaimsNormalizer
{
    public function normalize(string $samlResponseXml): Claims;
}
