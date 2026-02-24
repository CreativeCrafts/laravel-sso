<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlAssertionConditionsValidator;
use CreativeCrafts\LaravelSso\Contracts\Protocol\Saml\SamlSignatureValidator;
use CreativeCrafts\LaravelSso\Models\IdentityProvider;
use CreativeCrafts\LaravelSso\Models\Tenant;
use CreativeCrafts\LaravelSso\Protocol\Saml\Dto\SamlSignedXml;
use CreativeCrafts\LaravelSso\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

it('invokes signature validation and conditions validation then returns 204', function () {
    // Make sure we fail loudly if the controller throws.
    $this->withoutExceptionHandling();

    config()->set('sso.saml.sp.entity_id', null);
    config()->set('sso.saml.clock_skew_seconds', 60);
    config()->set('sso.saml.require_audience', true);
    config()->set('sso.saml.require_recipient', true);
    config()->set('sso.saml.require_destination', true);

    $tenant = Tenant::query()->create([
      'ulid' => (string)Str::ulid(),
      'name' => 'T1',
    ]);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => ['---CERT---'],
      ],
    ]);

    $xml = '<xml>signed</xml>';

    $signed = new SamlSignedXml(
        document: new DOMDocument('1.0', 'UTF-8'),
        validatedResponseSignature: false,
        validatedAssertionSignature: true,
    );

    app()->instance(
        SamlSignatureValidator::class,
        new class ($xml, $signed) implements SamlSignatureValidator {
          public function __construct(
              private readonly string $expectedXml,
              private readonly SamlSignedXml $return,
          ) {
          }

          public function validate(string $xml, array $signingCertificatesPem): SamlSignedXml
          {
              expect($xml)
                ->toBe($this->expectedXml)
                ->and($signingCertificatesPem)->toBe(['---CERT---']);

              return $this->return;
          }
      },
    );

    app()->instance(
        SamlAssertionConditionsValidator::class,
        new class ($tenant, $idp) implements SamlAssertionConditionsValidator {
          public function __construct(
              private readonly Tenant $tenant,
              private readonly IdentityProvider $idp,
          ) {
          }

          public function validate(
              SamlSignedXml $signed,
              string $expectedAudience,
              string $expectedRecipient,
              string $expectedDestination,
              int $clockSkewSeconds,
              bool $requireAudience,
              bool $requireRecipient,
              bool $requireDestination,
          ): void {
              $acsUrl = route('sso.saml.acs', ['tenant' => $this->tenant->ulid, 'idp' => (string)$this->idp->id], true);
              $metadataUrl = route('sso.saml.metadata', ['tenant' => $this->tenant->ulid, 'idp' => (string)$this->idp->id], true);

              // When entity_id is null, controller uses metadata URL as expected audience
              expect($expectedAudience)
                ->toBe($metadataUrl)
                ->and($expectedRecipient)->toBe($acsUrl)
                ->and($expectedDestination)->toBe($acsUrl)
                ->and($clockSkewSeconds)->toBe(60)
                ->and($requireAudience)->toBeTrue()
                ->and($requireRecipient)->toBeTrue()
                ->and($requireDestination)->toBeTrue();
          }
      },
    );

    $acsUrl = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $resp = $this->post($acsUrl, [
      'SAMLResponse' => base64_encode($xml),
    ]);

    $resp->assertNoContent();
});

it('returns 500 when SAMLResponse is missing', function () {
    $tenant = Tenant::query()->create([
      'ulid' => (string)Str::ulid(),
      'name' => 'T1',
    ]);

    $idp = IdentityProvider::query()->create([
      'tenant_id' => $tenant->id,
      'name' => 'SAML IdP',
      'protocol' => 'saml',
      'enabled' => true,
      'config' => [
        'saml_signing_certs_pem' => [],
      ],
    ]);

    $acsUrl = route('sso.saml.acs', ['tenant' => $tenant->ulid, 'idp' => (string)$idp->id], true);

    $resp = $this->post($acsUrl, []);

    // In feature tests, exceptions typically become 500 unless you handle them explicitly.
    $resp->assertStatus(500);
});
