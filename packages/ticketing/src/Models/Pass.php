<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Ticketing\Database\Factories\PassFactory;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Events\PassRevoked;
use AIArmada\Ticketing\Events\PassVoided;
use AIArmada\Ticketing\States\Activated;
use AIArmada\Ticketing\States\Cancelled;
use AIArmada\Ticketing\States\Expired;
use AIArmada\Ticketing\States\PassState;
use AIArmada\Ticketing\States\Revoked;
use AIArmada\Ticketing\States\Used;
use AIArmada\Ticketing\States\Voided;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

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
 * @property-read TicketType|null $ticketType
 * @property-read PassHolder|null $holder
 * @property-read Collection<int, PassHolder> $holderHistory
 * @property-read Collection<int, PassTransfer> $transferHistory
 */
class Pass extends Model
{
    use HasFactory;
    use HasOwner;
    use HasUuids;

    protected static function newFactory(): PassFactory
    {
        return PassFactory::new();
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
        return config('ticketing.database.tables.passes', 'passes');
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
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
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

        return ! in_array($status, [
            'used',
            'revoked',
            'voided',
            'expired',
        ], true);
    }

    public function markActivated(): void
    {
        $this->activated_at ??= now();
        $this->status->transitionTo(Activated::class);
    }

    public function markUsed(): void
    {
        $this->used_at ??= now();
        $this->status->transitionTo(Used::class);
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->cancelled_at ??= now();
        $this->status_reason = $reason;
        $this->status->transitionTo(Cancelled::class);

        Event::dispatch(new PassCancelled($this));
    }

    public function markRevoked(?string $reason = null): void
    {
        $this->revoked_at ??= now();
        $this->status_reason = $reason;
        $this->status->transitionTo(Revoked::class);

        Event::dispatch(new PassRevoked($this));
    }

    public function markVoided(?string $reason = null): void
    {
        $this->voided_at ??= now();
        $this->status_reason = $reason;
        $this->status->transitionTo(Voided::class);

        Event::dispatch(new PassVoided($this));
    }

    public function markExpired(): void
    {
        $this->expired_at ??= now();
        $this->status->transitionTo(Expired::class);

        Event::dispatch(new PassExpired($this));
    }

    public function generateQrCode(): string
    {
        return Str::random(32);
    }

    public function generateBarcode(): string
    {
        return (string) str()->random(16);
    }
}
