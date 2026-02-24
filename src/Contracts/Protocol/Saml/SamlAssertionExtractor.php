<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

interface SamlAssertionExtractor
{
    /**
     * @return array{nameId: string, attributes: array<string, array<int, string>>}
     */
    public function extract(string $samlResponseXml): array;
}
