<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Seating\Features\Reservations\Models\SeatAllocation;
use AIArmada\Ticketing\Database\Factories\PassFactory;
use AIArmada\Ticketing\Features\Passes\Actions\DeletePassDependents;
use AIArmada\Ticketing\Platform\ModelResolver;
use AIArmada\Ticketing\States\PassState;
use AIArmada\Ticketing\Support\TicketingOwnerGuard;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $ticketable_type
 * @property string $ticketable_id
 * @property string|null $ticket_type_id
 * @property string|null $registration_type
 * @property string|null $registration_id
 * @property string|null $occurrence_id
 * @property string|null $session_id
 * @property string $pass_no
 * @property string|null $qr_code
 * @property string|null $barcode
 * @property PassState|string $status
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $voided_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $transfer_expires_at
 * @property string|null $status_reason
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $ticketable
 * @property-read Model|Eloquent|null $registration
 * @property-read Model|null $ticketType
 * @property-read PassHolder|null $holder
 * @property-read Collection<int, PassHolder> $holderHistory
 * @property-read Collection<int, PassTransfer> $transferHistory
 */
class Pass extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'ticketing.owner';

    protected static function newFactory(): PassFactory
    {
        return PassFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $pass): void {
            TicketingOwnerGuard::assertRelations($pass, [
                ['relation' => 'ticketType'],
                ['relation' => 'ticketable', 'required' => true],
                ['relation' => 'registration'],
            ]);
        });

        static::deleting(function (self $pass): void {
            app(DeletePassDependents::class)->handle($pass);
        });
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ticketable_type',
        'ticketable_id',
        'ticket_type_id',
        'registration_type',
        'registration_id',
        'occurrence_id',
        'session_id',
        'pass_no',
        'qr_code',
        'barcode',
        'status_reason',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.passes', 'ticket_passes');
    }

    protected function casts(): array
    {
        return [
            'status' => PassState::class,
            'issued_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'transfer_expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function ticketable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function registration(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Pass>  $query
     */
    public function scopeForOccurrence(Builder $query, string $occurrenceId): Builder
    {
        return $query->where('occurrence_id', $occurrenceId);
    }

    /**
     * @param  Builder<Pass>  $query
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::ticketTypeClass());
    }

    /**
     * @return HasOne<PassHolder, $this>
     */
    public function holder(): HasOne
    {
        return $this->hasOne(PassHolder::class)->where('is_current', true);
    }

    /**
     * @return HasMany<PassHolder, $this>
     */
    public function holderHistory(): HasMany
    {
        return $this->hasMany(PassHolder::class);
    }

    /**
     * @return HasMany<PassTransfer, $this>
     */
    public function transferHistory(): HasMany
    {
        return $this->hasMany(PassTransfer::class);
    }

    /**
     * @return MorphMany<SeatAllocation, $this>
     */
    public function seatAllocations(): MorphMany
    {
        return $this->morphMany(SeatAllocation::class, 'allocated_to');
    }

    public function isValid(): bool
    {
        $status = $this->status;

        if ($status instanceof PassState) {
            $status = $status::getMorphClass();
        }

        if (! in_array($status, ['issued', 'activated'], true)) {
            return false;
        }

        if ($this->registration_type === null && $this->registration_id === null) {
            return true;
        }

        if (blank($this->registration_type) || blank($this->registration_id)) {
            return false;
        }

        $registration = $this->registration;

        if (! $registration instanceof Model) {
            return false;
        }

        $registrationStatus = $registration->getAttribute('status');
        $registrationStatus = is_object($registrationStatus) && method_exists($registrationStatus, 'getValue')
            ? $registrationStatus->getValue()
            : $registrationStatus;

        return ! in_array($registrationStatus, [
            'refund_pending',
            'refunded',
            'cancelled',
            'canceled',
            'rejected',
            'expired',
        ], true);
    }
}
