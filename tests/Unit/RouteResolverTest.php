<?php

declare(strict_types=1);

use CreativeCrafts\LaravelSso\Core\ConnectionRouteResolver;
use CreativeCrafts\LaravelSso\Core\IdentityProviderRouteResolver;
use CreativeCrafts\LaravelSso\Exceptions\TenantScopedRecordNotFound;
use CreativeCrafts\LaravelSso\Repositories\EloquentConnectionRepository;
use CreativeCrafts\LaravelSso\Repositories\EloquentIdentityProviderRepository;
use CreativeCrafts\LaravelSso\Tests\Support\SsoTestHelpers;

uses(SsoTestHelpers::class);

it('resolves connection ids and public route keys', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);

    $resolver = new ConnectionRouteResolver(new EloquentConnectionRepository());

    expect($resolver->resolveId($tenant, (string) $connection->ulid))->toBe((int) $connection->id)
        ->and($resolver->publicRouteKey($connection))->toBe((string) $connection->ulid);
});

it('throws when a connection route key is unknown for the tenant', function (): void {
    $tenant = $this->createTenant();
    $resolver = new ConnectionRouteResolver(new EloquentConnectionRepository());

    $resolver->resolveId($tenant, '01JUNKNOWN0000000000000000');
})->throws(TenantScopedRecordNotFound::class);

it('throws when a connection is missing a public ulid', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $connection = $this->createConnection($tenant, $idp);
    $connection->ulid = null;
    $connection->save();

    $resolver = new ConnectionRouteResolver(new EloquentConnectionRepository());

    $resolver->publicRouteKey($connection->fresh());
})->throws(RuntimeException::class);

it('resolves identity provider ids and public route keys', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);

    $resolver = new IdentityProviderRouteResolver(new EloquentIdentityProviderRepository());

    expect($resolver->resolveId($tenant, (string) $idp->ulid))->toBe((int) $idp->id)
        ->and($resolver->publicRouteKey($idp))->toBe((string) $idp->ulid);
});

it('throws when an identity provider route key is unknown for the tenant', function (): void {
    $tenant = $this->createTenant();
    $resolver = new IdentityProviderRouteResolver(new EloquentIdentityProviderRepository());

    $resolver->resolveId($tenant, '01JUNKNOWN0000000000000000');
})->throws(TenantScopedRecordNotFound::class);

it('throws when an identity provider is missing a public ulid', function (): void {
    $tenant = $this->createTenant();
    $idp = $this->createIdentityProvider($tenant);
    $idp->ulid = null;
    $idp->save();

    $resolver = new IdentityProviderRouteResolver(new EloquentIdentityProviderRepository());

    $resolver->publicRouteKey($idp->fresh());
})->throws(RuntimeException::class);
