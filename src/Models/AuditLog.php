<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuditLog extends Model
{
    protected $table = 'sso_audit_logs';

    protected $fillable = [
      'tenant_id',
      'identity_provider_id',
      'connection_id',
      'auth_attempt_id',
      'event',
      'level',
      'ip',
      'user_agent',
      'context',
      'occurred_at',
    ];

    protected $casts = [
      'context' => 'array',
      'occurred_at' => 'datetime',
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
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    /**
     * @return BelongsTo<AuthAttempt, $this>
     */
    public function authAttempt(): BelongsTo
    {
        return $this->belongsTo(AuthAttempt::class, 'auth_attempt_id');
    }
}
