<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $delivery_id
 * @property string $kind
 * @property string $token_hash
 * @property string|null $target_url_ciphertext
 * @property string|null $target_host
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $first_used_at
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunicationDelivery $delivery
 * @property-read Model|Eloquent $owner
 */
final class CommunicationTrackingToken extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'delivery_id',
        'kind',
        'token_hash',
        'target_url_ciphertext',
        'target_host',
        'expires_at',
        'first_used_at',
        'last_used_at',
        'revoked_at',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.tracking_tokens', 'communication_tracking_tokens');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'first_used_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommunicationDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CommunicationDelivery::class, 'delivery_id');
    }
}
