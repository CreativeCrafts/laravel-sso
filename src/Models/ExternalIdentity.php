<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $identity_provider_id
 * @property string $provider_subject
 * @property string|null $email
 * @property string|null $display_name
 * @property string $authenticatable_type
 * @property int|string $authenticatable_id
 * @property array<string, mixed> $claims
 */
final class ExternalIdentity extends Model
{
    protected $table = 'sso_external_identities';

    protected $fillable = [
      'tenant_id',
      'identity_provider_id',
      'provider_subject',
      'email',
      'display_name',
      'authenticatable_type',
      'authenticatable_id',
      'claims',
    ];

    protected $casts = [
      'claims' => 'array',
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
     * @return MorphTo<Model, $this>
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
