<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

/**
 * @property string|null $url
 * @property string|null $event
 * @property string|null $event_type
 * @property string $status
 * @property array<string>|null $events
 * @property array<string, mixed>|null $payload
 * @property array<string, string>|null $headers
 * @property bool $all_events
 * @property bool $verified
 * @property bool $processed
 * @property string|null $idempotency_key
 * @property int $retry_count
 * @property CarbonImmutable|null $last_retry_at
 * @property string|null $last_error
 * @property float|null $processing_time_ms
 * @property string|null $ip_address
 * @property int|null $created_on
 * @property int|null $updated_on
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Webhook extends WebhookCall
{
    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;

    public const WEBHOOK_NAME = 'chip.webhook';

    protected static string $ownerScopeConfigKey = 'chip.owner';

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'headers',
        'payload',
        'exception',
        'title',
        'events',
        'callback',
        'all_events',
        'public_key',
        'event_type',
        'event',
        'signature',
        'verified',
        'processed',
        'processing_error',
        'processing_attempts',
        'idempotency_key',
        'retry_count',
        'last_retry_at',
        'last_error',
        'processing_time_ms',
        'ip_address',
        'created_on',
        'updated_on',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'exception' => 'array',
        'events' => 'array',
        'all_events' => 'boolean',
        'verified' => 'boolean',
        'processed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'retry_count' => 'integer',
        'processing_attempts' => 'integer',
        'processing_time_ms' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('chip_webhook_calls', function (Builder $builder): void {
            $builder
                ->where('name', self::WEBHOOK_NAME)
                ->whereNotNull('event_type');
        });

        static::creating(function (Webhook $webhook): void {
            $webhook->setAttribute('name', $webhook->getAttribute('name') ?? self::WEBHOOK_NAME);
            $webhook->setAttribute('url', $webhook->getAttribute('url') ?? (string) ($webhook->getAttribute('callback') ?? ''));

            if ($webhook->getAttribute('event_type') === null) {
                $webhook->setAttribute('event_type', $webhook->getAttribute('event'));
            }
        });
    }

    #[Override]
    public function getTable(): string
    {
        return 'webhook_calls';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return $query;
        }

        if ($owner === OwnerContext::CURRENT) {
            $owner = OwnerContext::resolve();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                sprintf('%s requires an owner context or explicit global context.', static::class),
            );
        }

        $includeGlobal ??= (bool) config('chip.owner.include_global', false);

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    /**
     * Mark the webhook as processed.
     */
    public function markProcessed(float $processingTimeMs = 0): self
    {
        $this->forceFill([
            'status' => 'processed',
            'processed' => true,
            'processed_at' => now(),
            'processing_time_ms' => $processingTimeMs,
        ])->save();

        return $this;
    }

    /**
     * Mark the webhook as failed.
     */
    public function markFailed(Throwable $exception): self
    {
        $this->forceFill([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
        ])->save();

        return $this;
    }

    /**
     * Mark the webhook for retry.
     */
    public function markForRetry(string $reason): self
    {
        $this->forceFill([
            'status' => 'failed',
            'last_error' => $reason,
        ])->save();

        return $this;
    }

    protected function toTimestamp(?int $value): ?CarbonImmutable
    {
        return $value !== null ? CarbonImmutable::createFromTimestampUTC($value) : null;
    }
}
