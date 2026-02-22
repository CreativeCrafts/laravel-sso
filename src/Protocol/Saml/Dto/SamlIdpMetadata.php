<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Protocol\Saml\Dto;

final readonly class SamlIdpMetadata
{
    /**
     * @param array<int, string> $signingCertificatesPem
     */
    public function __construct(
        public string $entityId,
        public ?string $ssoRedirectUrl,
        public ?string $ssoPostUrl,
        public ?string $sloRedirectUrl,
        public ?string $sloPostUrl,
        public array $signingCertificatesPem,
    ) {
    }

    public function primarySigningCertificatePem(): ?string
    {
        return $this->signingCertificatesPem[0] ?? null;
    }
}
