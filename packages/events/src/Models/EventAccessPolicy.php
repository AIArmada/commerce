<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventAccessPolicyFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property bool $registration_required
 * @property bool $approval_required
 * @property bool $payment_required
 * @property bool $ticket_required
 * @property bool $seating_required
 * @property bool $walk_in_allowed
 * @property int|null $capacity
 * @property bool $waitlist_enabled
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventAccessPolicy extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'registration_required', 'approval_required', 'payment_required',
        'ticket_required', 'seating_required', 'walk_in_allowed',
        'capacity', 'waitlist_enabled',
        'opens_at', 'closes_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_access_policies', 'event_access_policies');
    }

    protected function casts(): array
    {
        return [
            'registration_required' => 'boolean',
            'approval_required' => 'boolean',
            'payment_required' => 'boolean',
            'ticket_required' => 'boolean',
            'seating_required' => 'boolean',
            'walk_in_allowed' => 'boolean',
            'capacity' => 'integer',
            'waitlist_enabled' => 'boolean',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): EventAccessPolicyFactory
    {
        return EventAccessPolicyFactory::new();
    }
}
