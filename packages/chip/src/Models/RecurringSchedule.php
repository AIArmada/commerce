<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App-layer recurring payment schedule using Chip's token + charge APIs.
 *
 * @property string $id
 * @property string $chip_client_id
 * @property string $recurring_token_id
 * @property string|null $subscriber_type
 * @property string|null $subscriber_id
 * @property RecurringStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property RecurringInterval $interval
 * @property int $interval_count
 * @property CarbonImmutable|null $next_charge_at
 * @property CarbonImmutable|null $last_charged_at
 * @property int $failure_count
 * @property int $max_failures
 * @property CarbonImmutable|null $cancelled_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, RecurringCharge> $charges
 */
class RecurringSchedule extends ChipModel
{
    public $timestamps = true;

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<RecurringCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(RecurringCharge::class, 'schedule_id');
    }

    public function isActive(): bool
    {
        return $this->status === RecurringStatus::Active;
    }

    public function isPaused(): bool
    {
        return $this->status === RecurringStatus::Paused;
    }

    public function isCancelled(): bool
    {
        return $this->status === RecurringStatus::Cancelled;
    }

    public function isFailed(): bool
    {
        return $this->status === RecurringStatus::Failed;
    }

    public function isDue(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->next_charge_at !== null && $this->next_charge_at->isPast();
    }

    public function calculateNextChargeDate(): CarbonImmutable
    {
        $base = $this->last_charged_at ?? CarbonImmutable::now();

        // Ensure we're working with CarbonImmutable
        if (! $base instanceof CarbonImmutable) {
            $base = CarbonImmutable::parse($base);
        }

        return match ($this->interval) {
            RecurringInterval::Daily => $base->addDays($this->interval_count),
            RecurringInterval::Weekly => $base->addWeeks($this->interval_count),
            RecurringInterval::Monthly => $base->addMonths($this->interval_count),
            RecurringInterval::Yearly => $base->addYears($this->interval_count),
        };
    }

    public function getAmountFormatted(): string
    {
        return number_format($this->amount_minor / 100, 2) . ' ' . $this->currency;
    }

    protected static function tableSuffix(): string
    {
        return 'recurring_schedules';
    }

    protected static function booted(): void
    {
        static::deleting(function (self $schedule): void {
            $schedule->charges()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecurringStatus::class,
            'interval' => RecurringInterval::class,
            'next_charge_at' => 'datetime',
            'last_charged_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
