<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Models;

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Jnt\Features\Orders\Api\ReadTrackingOrderOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string|null $order_id
 * @property string|null $tracking_number
 * @property string|null $order_reference
 * @property string|null $digest
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 * @property string $processing_status
 * @property string|null $processing_error
 * @property CarbonImmutable|null $processed_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static> forOwner(?Model $owner, bool $includeGlobal = false)
 */
final class JntWebhookLog extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use LogsCommerceActivity;

    public const WEBHOOK_NAME = 'jnt.webhooks.status';

    protected static string $ownerScopeConfigKey = 'jnt.owner';

    protected static function booted(): void
    {
        static::addGlobalScope('jnt_webhook_calls', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('name'), self::WEBHOOK_NAME);
        });

        static::creating(function (JntWebhookLog $log): void {
            $log->setAttribute('name', $log->getAttribute('name') ?? self::WEBHOOK_NAME);
            $log->setAttribute('url', $log->getAttribute('url') ?? '');

            if ($log->order_id === null) {
                return;
            }

            try {
                $order = app(ReadTrackingOrderOwner::class)->findForLog($log->order_id);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('Invalid order_id for JntWebhookLog.', previous: $exception);
            }

            if ($order === null) {
                throw new InvalidArgumentException('Invalid order_id for JntWebhookLog.');
            }

            if ($log->owner_type !== null || $log->owner_id !== null) {
                if ($order->ownerType !== $log->owner_type
                    || (string) $order->ownerId !== (string) $log->owner_id) {
                    throw new InvalidArgumentException(
                        'JntWebhookLog order_id belongs to a different owner than the current context.',
                    );
                }

                return;
            }

            $log->owner_type = $order->ownerType;
            $log->owner_id = $order->ownerId;
        });
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'order_id',
        'tracking_number',
        'order_reference',
        'digest',
        'headers',
        'payload',
        'processing_status',
        'processing_error',
        'processed_at',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);

        return $tables['webhook_calls'] ?? 'webhook_calls';
    }

    /**
     * Check if the webhook has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the webhook processing failed.
     */
    public function isFailed(): bool
    {
        return $this->processing_status === self::STATUS_FAILED;
    }

    /**
     * Check if the webhook is pending processing.
     */
    public function isPending(): bool
    {
        return $this->processing_status === self::STATUS_PENDING;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'exception' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
