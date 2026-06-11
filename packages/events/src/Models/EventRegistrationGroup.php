<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use RuntimeException;

/**
 * @property string $id
 * @property string $occurrence_id
 * @property string $code
 * @property string $status
 * @property string $check_in_mode
 * @property int|null $size
 */
class EventRegistrationGroup extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'occurrence_id', 'seat_category_id',
        'purchaser_customer_id', 'order_id',
        'name', 'code', 'status', 'size', 'check_in_mode',
        'filled_at', 'cancelled_at', 'metadata',
    ];

    protected $attributes = ['status' => 'draft', 'check_in_mode' => 'per_member'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'filled_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            if (! is_string($group->code) || mb_trim($group->code) === '') {
                $group->code = static::generateUniqueCode();
            }
        });
    }

    public function getTable(): string
    {
        return config('events.database.tables.registration_groups', 'event_registration_groups');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'registration_group_id');
    }

    public static function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = mb_strtoupper(Str::random(8));

            if (! static::query()->withoutOwnerScope()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique registration group code.');
    }
}
