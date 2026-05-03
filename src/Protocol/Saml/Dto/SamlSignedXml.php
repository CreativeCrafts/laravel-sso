<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml\Dto;

use DOMDocument;

final readonly class SamlSignedXml
{
    public function __construct(
        public DOMDocument $document,
        public bool $validatedResponseSignature,
        public bool $validatedAssertionSignature,
        public ?string $validatedResponseId = null,
        public ?string $validatedAssertionId = null,
    ) {
    }
}
