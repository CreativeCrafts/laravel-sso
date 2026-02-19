<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property string|null $name
 * @property array<string, mixed> $metadata
 */
final class Tenant extends Model
{
    protected $table = 'sso_tenants';

    protected $fillable = [
      'ulid',
      'name',
      'metadata',
    ];

    protected $casts = [
      'metadata' => 'array',
    ];

    /**
     * @return HasMany<IdentityProvider, $this>
     */
    public function identityProviders(): HasMany
    {
        return $this->hasMany(IdentityProvider::class, 'tenant_id');
    }

    /**
     * @return HasMany<Connection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'tenant_id');
    }

    /**
     * @return HasMany<AuthAttempt, $this>
     */
    public function authAttempts(): HasMany
    {
        return $this->hasMany(AuthAttempt::class, 'tenant_id');
    }

    /**
     * @return HasMany<ExternalIdentity, $this>
     */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(ExternalIdentity::class, 'tenant_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'tenant_id');
    }
}
