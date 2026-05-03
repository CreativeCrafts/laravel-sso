<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;

interface SamlAssertionExtractor
{
    /**
     * @return array{nameId: string, attributes: array<string, array<int, string>>}
     */
    public function extract(SamlSignedXml $signed): array;
}
