<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_term_id
 * @property string $policy_code
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EventTerm $term
 *
 * @use HasFactory<\AIArmada\Events\Database\Factories\EventTermPolicyFactory>
 */
final class EventTermPolicy extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_term_id',
        'policy_code',
        'is_enabled',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_term_policies', 'event_term_policies');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<EventTerm, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(EventTerm::class, 'event_term_id');
    }
}
