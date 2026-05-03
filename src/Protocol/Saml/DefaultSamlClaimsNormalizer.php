<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml;

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionExtractor;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsMapper;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlClaimsNormalizer;
use CreativeCrafts\LaravelSso\Core\Dto\Claims;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;

final readonly class DefaultSamlClaimsNormalizer implements SamlClaimsNormalizer
{
    public function __construct(
        private SamlAssertionExtractor $extractor,
        private SamlClaimsMapper $mapper,
    ) {
    }

    public function normalize(SamlSignedXml $signed): Claims
    {
        $extracted = $this->extractor->extract($signed);

        /** @var string $nameId */
        $nameId = $extracted['nameId'];

        /** @var array<string, array<int, string>> $attributes */
        $attributes = $extracted['attributes'];

        return $this->mapper->map($nameId, $attributes);
    }
}
