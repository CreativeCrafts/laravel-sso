<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Connection extends Model
{
    protected $table = 'sso_connections';

    protected $fillable = [
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
