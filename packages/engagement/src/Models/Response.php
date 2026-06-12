<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\Engagement\Database\Factories\ResponseFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $responder_type
 * @property string $responder_id
 * @property string $respondable_type
 * @property string $respondable_id
 * @property string $response_type
 * @property string $status
 * @property string $visibility
 * @property \Carbon\CarbonImmutable|null $responded_at
 * @property \Carbon\CarbonImmutable|null $changed_at
 * @property \Carbon\CarbonImmutable|null $cancelled_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property string|null $source
 * @property array|null $metadata
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class Response extends Model
{
    use HasFactory;
    use UsesEngagementUuid;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CHANGED = 'changed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'responder_type',
        'responder_id',
        'respondable_type',
        'respondable_id',
        'response_type',
        'status',
        'visibility',
        'responded_at',
        'changed_at',
        'cancelled_at',
        'expires_at',
        'source',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.responses', 'responses');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function responder(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function respondable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param Builder<Response> $query
     * @return Builder<Response>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param Builder<Response> $query
     * @return Builder<Response>
     */
    public function scopeResponseType(Builder $query, string $type): Builder
    {
        return $query->where('response_type', $type);
    }

    /**
     * @param Builder<Response> $query
     * @return Builder<Response>
     */
    public function scopeForResponder(Builder $query, Model $responder): Builder
    {
        return $query->where('responder_type', $responder->getMorphClass())
            ->where('responder_id', $responder->getKey());
    }

    /**
     * @param Builder<Response> $query
     * @return Builder<Response>
     */
    public function scopeForRespondable(Builder $query, Model $respondable): Builder
    {
        return $query->where('respondable_type', $respondable->getMorphClass())
            ->where('respondable_id', $respondable->getKey());
    }

    /**
     * @param Builder<Response> $query
     * @return Builder<Response>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    protected static function newFactory(): ResponseFactory
    {
        return ResponseFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'responded_at' => 'immutable_datetime',
            'changed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
