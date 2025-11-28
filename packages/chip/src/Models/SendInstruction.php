<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $amount
 * @property string|null $state
 * @property string|null $currency
 * @property string|null $reference
 * @property string|null $bank_account_id
 */
class SendInstruction extends ChipModel
{
    public $timestamps = false;

    /** @return Attribute<float, never> */
    public function amountNumeric(): Attribute
    {
        return Attribute::get(fn (): float => (float) $this->amount);
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
