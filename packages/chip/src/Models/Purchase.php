<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property string|null $status
 * @property array<string, mixed>|null $purchase
 * @property array<string, mixed>|null $client
 * @property array<string, mixed>|null $payment
 * @property array<string, mixed>|null $issuer_details
 * @property array<string, mixed>|null $transaction_data
 * @property array<array<string, mixed>>|null $status_history
 * @property array<string, mixed>|null $currency_conversion
 * @property array<string>|null $payment_method_whitelist
 * @property string|null $checkout_url
 * @property int|null $paid_on
 * @property bool $send_receipt
 * @property bool $is_test
 * @property bool $is_recurring_token
 * @property bool $skip_capture
 * @property bool $force_recurring
 * @property bool $marked_as_paid
 * @property int|null $created_on
 * @property int|null $updated_on
 * @property int|null $due
 * @property int|null $viewed_on
 * @property-read Money|null $totalMoney
 * @property-read array<int, array{status: string, timestamp: Carbon|null, translated: string}> $timeline
 */
class Purchase extends ChipModel
{
    public function amount(): Attribute
    {
        return Attribute::get(function (): ?int {
            $amount = Arr::get($this->purchase, 'amount');

            return is_numeric($amount) ? (int) $amount : null;
        });
    }

    public function currency(): Attribute
    {
        return Attribute::get(function (): ?string {
            $currency = Arr::get($this->purchase, 'currency');

            return is_string($currency) ? $currency : null;
        });
    }

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    public function dueOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['due'] ?? null));
    }

    public function viewedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['viewed_on'] ?? null));
    }

    public function clientEmail(): Attribute
    {
        return Attribute::get(fn (): ?string => Arr::get($this->client, 'email'));
    }

    public function totalMoney(): Attribute
    {
        return Attribute::get(function (): ?Money {
            $currency = Arr::get($this->purchase, 'currency', 'MYR');
            $total = Arr::get($this->purchase, 'total');

            if ($total === null) {
                return null;
            }

            if (is_array($total)) {
                $total = Arr::get($total, 'amount');
                $currency = Arr::get($this->purchase, 'total.currency', $currency);
            }

            if (! is_numeric($total)) {
                return null;
            }

            return $this->toMoney((int) $total, is_string($currency) ? mb_strtoupper($currency) : 'MYR');
        });
    }

    public function formattedTotal(): Attribute
    {
        return Attribute::get(function (): ?string {
            $money = $this->totalMoney;

            if ($money === null) {
                return null;
            }

            return $money->format();
        });
    }

    public function statusColor(): string
    {
        $status = (string) ($this->status ?? '');

        return match ($status) {
            'paid', 'completed', 'captured' => 'success',
            'partially_paid', 'processing', 'refunding' => 'warning',
            'failed', 'cancelled', 'chargeback' => 'danger',
            default => 'secondary',
        };
    }

    public function statusBadge(): string
    {
        return (string) str($this->status ?? 'unknown')->headline();
    }

    public function timeline(): Attribute
    {
        return Attribute::get(function (): array {
            $history = $this->status_history ?? [];

            /** @var array<int, array<string, mixed>> $history */
            return collect($history)
                ->map(fn (array $entry): array => [
                    'status' => (string) ($entry['status'] ?? 'unknown'),
                    'timestamp' => isset($entry['timestamp']) ? Carbon::createFromTimestampUTC((int) $entry['timestamp']) : null,
                    'translated' => (string) str((string) ($entry['status'] ?? 'unknown'))->headline(),
                ])
                ->all();
        });
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'purchase_id');
    }

    protected static function tableSuffix(): string
    {
        return 'purchases';
    }

    protected static function booted(): void
    {
        static::deleting(function (self $purchase): void {
            $purchase->payments()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client' => 'array',
            'purchase' => 'array',
            'payment' => 'array',
            'issuer_details' => 'array',
            'transaction_data' => 'array',
            'status_history' => 'array',
            'currency_conversion' => 'array',
            'payment_method_whitelist' => 'array',
            'metadata' => 'array',
            'send_receipt' => 'boolean',
            'is_test' => 'boolean',
            'is_recurring_token' => 'boolean',
            'skip_capture' => 'boolean',
            'force_recurring' => 'boolean',
            'marked_as_paid' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
