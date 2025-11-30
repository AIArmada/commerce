<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property string $amount
 * @property string $email
 * @property string $description
 * @property string $reference
 * @property string|null $state
 * @property string|null $receipt_url
 * @property string|null $slug
 */
class SendInstruction extends ChipIntegerModel
{
    /** @return Attribute<float, never> */
    public function amountNumeric(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->amount);
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
            'completed', 'processed' => 'success',
            'received', 'queued', 'verifying' => 'warning',
            'failed', 'cancelled', 'rejected' => 'danger',
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
        ];
    }
}
