<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $ulid
 * @property int $tenant_id
 * @property int $identity_provider_id
 * @property string $name
 * @property bool $enabled
 * @property string|null $guard
 * @property array<string, mixed> $settings
 */
final class Connection extends Model
{
    protected $table = 'sso_connections';

    protected $fillable = [
      'ulid',
      'tenant_id',
      'identity_provider_id',
      'name',
      'enabled',
      'guard',
      'settings',
    ];

    protected $casts = [
      'enabled' => 'bool',
      'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Connection $connection): void {
            if ($connection->ulid === null || $connection->ulid === '') {
                $connection->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<IdentityProvider, $this>
     */
    public function identityProvider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class, 'identity_provider_id');
    }

    /**
     * @return HasMany<AuthAttempt, $this>
     */
    public function authAttempts(): HasMany
    {
        return $this->hasMany(AuthAttempt::class, 'connection_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'connection_id');
    }
}
