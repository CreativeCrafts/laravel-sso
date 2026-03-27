<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $protocol
 * @property bool $enabled
 * @property array<string, mixed>|null $config
 */
final class IdentityProvider extends Model
{
    protected $table = 'sso_identity_providers';

    protected $fillable = [
      'tenant_id',
      'name',
      'protocol',
      'enabled',
      'config',
    ];

    protected $casts = [
      'enabled' => 'bool',
      'config' => 'encrypted:array',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return HasMany<Connection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'identity_provider_id');
    }

    /**
     * @return HasMany<AuthAttempt, $this>
     */
    public function authAttempts(): HasMany
    {
        return $this->hasMany(AuthAttempt::class, 'identity_provider_id');
    }

    /**
     * @return HasMany<ExternalIdentity, $this>
     */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(ExternalIdentity::class, 'identity_provider_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'identity_provider_id');
    }
}
