<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;

interface SamlSignatureValidator
{
    /**
     * @param array<int, string> $signingCertificatesPem
     */
    public function validate(string $xml, array $signingCertificatesPem): SamlSignedXml;
}
