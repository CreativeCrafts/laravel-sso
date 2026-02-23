<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Contracts\Protocol\Saml;

use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;

interface SamlAssertionConditionsValidator
{
    public function validate(
        SamlSignedXml $signed,
        string $expectedAudience,
        string $expectedRecipient,
        string $expectedDestination,
        int $clockSkewSeconds,
        bool $requireAudience,
        bool $requireRecipient,
        bool $requireDestination,
    ): void;
}
