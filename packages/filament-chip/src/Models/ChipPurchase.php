<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed> $purchase
 * @property array<string, mixed> $client
 * @property array<array<string, mixed>> $timeline
 */
final class ChipPurchase extends ChipModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function amount(): Attribute
    {
        return Attribute::get(fn () => Arr::get($this->purchase, 'amount'));
    }

    public function currency(): Attribute
    {
        return Attribute::get(fn () => Arr::get($this->purchase, 'currency'));
    }

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    public function dueOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['due'] ?? null));
    }

    public function viewedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['viewed_on'] ?? null));
    }

    public function clientEmail(): Attribute
    {
        return Attribute::get(fn () => Arr::get($this->client, 'email'));
    }

    public function formattedTotal(): Attribute
    {
        return Attribute::get(function (): ?string {
            $currency = Arr::get($this->purchase, 'currency');
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

            return $this->formatMoney((int) $total, is_string($currency) ? mb_strtoupper($currency) : null);
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
                    'status' => $entry['status'] ?? 'unknown',
                    'timestamp' => isset($entry['timestamp']) ? Carbon::createFromTimestampUTC((int) $entry['timestamp']) : null,
                    'translated' => str($entry['status'] ?? 'unknown')->headline(),
                ])
                ->all();
        });
    }

    protected static function tableSuffix(): string
    {
        return 'purchases';
    }

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
