<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $connection_id
 * @property int|null $identity_provider_id
 * @property string $protocol
 * @property string $state
 * @property string|null $nonce
 * @property string|null $code_verifier
 * @property string|null $redirect_to
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed> $context
 */
final class AuthAttempt extends Model
{
    protected $table = 'sso_auth_attempts';

    protected $fillable = [
      'tenant_id',
      'connection_id',
      'identity_provider_id',
      'protocol',
      'state',
      'nonce',
      'code_verifier',
      'redirect_to',
      'expires_at',
      'consumed_at',
      'ip',
      'user_agent',
      'context',
    ];

    protected $casts = [
      'expires_at' => 'datetime',
      'consumed_at' => 'datetime',
      'context' => 'array',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    /**
     * @return BelongsTo<IdentityProvider, $this>
     */
    public function identityProvider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class, 'identity_provider_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auth_attempt_id');
    }

    public function isExpired(CarbonInterface $now): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
