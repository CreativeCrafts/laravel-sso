<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
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

    /**
     * @return Attribute<array<string, mixed>, never>
     */
    protected function settings(): Attribute
    {
        /** @var Attribute<array<string, mixed>, never> $attribute */
        $attribute = Attribute::get(
            function (mixed $value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);

                    if (is_array($decoded)) {
                        /** @var array<string, mixed> $normalized */
                        $normalized = [];

                        foreach ($decoded as $key => $item) {
                            $normalized[(string) $key] = $item;
                        }

                        return $normalized;
                    }
                }

                return [];
            },
        );

        return $attribute;
    }
}
