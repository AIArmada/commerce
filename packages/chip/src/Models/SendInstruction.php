<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property string $amount
 * @property string $email
 * @property string $description
 * @property string $reference
 * @property bool $send_recipient_receipt
 * @property string|null $state
 * @property string|null $receipt_url
 * @property string|null $slug
 */
class SendInstruction extends ChipIntegerModel
{
    public function amountMoney(): Attribute
    {
        return Attribute::get(function (): ?Money {
            $currency = (string) config('chip.defaults.currency', 'MYR');
            $amountInMinorUnits = MoneyFormatter::majorToMinor((string) $this->amount, $currency);

            return $this->toMoney($amountInMinorUnits, $currency);
        });
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /** @return Attribute<string, never> */
    public function stateLabel(): Attribute
    {
        return Attribute::get(fn (): string => (string) str($this->state ?? 'unknown')->headline());
    }

    public function stateColor(): string
    {
        $state = $this->state ?? '';

        return match ($state) {
            'completed' => 'success',
            'received', 'enquiring', 'executing', 'reviewing', 'accepted' => 'warning',
            'rejected', 'deleted' => 'danger',
            default => 'gray',
        };
    }

    protected static function tableSuffix(): string
    {
        return 'send_instructions';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'send_recipient_receipt' => 'boolean',
        ];
    }
}
