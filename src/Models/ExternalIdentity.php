<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ExternalIdentity extends Model
{
    protected $table = 'sso_external_identities';

    protected $fillable = [
      'tenant_id',
      'identity_provider_id',
      'provider_subject',
      'email',
      'authenticatable_type',
      'authenticatable_id',
      'attributes',
      'last_login_at',
    ];

    protected $casts = [
      'attributes' => 'array',
      'last_login_at' => 'datetime',
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
     * Polymorphic relation to the application's authenticatable model (user/admin/etc).
     *
     * @return MorphTo<Model, $this>
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'authenticatable_type', 'authenticatable_id');
    }
}
