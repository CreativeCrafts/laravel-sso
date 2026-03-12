<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Http\Controllers;

use CreativeCrafts\LaravelSso\Protocol\Saml\SpMetadataGenerator;
use DOMException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SamlSpMetadataController
{
    public function __construct(
        private SpMetadataGenerator $metadata,
    ) {
    }

    /**
     * @throws DOMException
     */
    public function __invoke(Request $request, string $tenant, string $connection): Response
    {
        $xml = $this->metadata->generate($tenant, $connection);

        return response($xml, 200)
            ->header('Content-Type', 'application/samlmetadata+xml; charset=UTF-8');
    }
}
