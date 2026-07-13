<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string $event_type
 * @property string $destination_key
 * @property string $endpoint
 * @property array|null $headers
 * @property string $body_json
 * @property string|null $signature
 * @property string $status
 * @property int $attempt_count
 * @property int $max_attempts
 * @property Carbon|null $available_at
 * @property Carbon|null $leased_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $dead_at
 * @property int|null $response_status
 * @property string|null $last_error_code
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class AffiliateWebhookDelivery extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_id',
        'event_type',
        'destination_key',
        'endpoint',
        'headers',
        'body_json',
        'signature',
        'status',
        'attempt_count',
        'max_attempts',
        'available_at',
        'leased_at',
        'last_attempt_at',
        'sent_at',
        'dead_at',
        'response_status',
        'last_error_code',
        'owner_type',
        'owner_id',
    ];

    protected $hidden = ['endpoint', 'headers', 'body_json', 'signature'];

    public function getTable(): string
    {
        return config('affiliates.database.tables.webhook_deliveries', 'affiliate_webhook_deliveries');
    }

    protected function casts(): array
    {
        return [
            'headers' => 'encrypted:array',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'leased_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'dead_at' => 'immutable_datetime',
            'response_status' => 'integer',
        ];
    }
}
