<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $id
 * @property string $account_number
 * @property string $bank_code
 * @property string $name
 * @property string $status
 * @property int|null $group_id
 * @property string|null $reference
 * @property bool $is_debiting_account
 * @property bool $is_crediting_account
 * @property string|null $rejection_reason
 */
class BankAccount extends IntegerIdChipModel
{
    public $timestamps = true;

    public function statusColor(): string
    {
        $status = $this->status ?? '';

        return match ($status) {
            'approved', 'active' => 'success',
            'pending', 'verifying' => 'warning',
            'rejected', 'disabled' => 'danger',
            default => 'gray',
        };
    }

    public function statusLabel(): string
    {
        return (string) str($this->status ?? 'unknown')->headline();
    }

    /** @return Attribute<bool, never> */
    public function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === 'active' || $this->status === 'approved');
    }

    protected static function tableSuffix(): string
    {
        return 'bank_accounts';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'is_debiting_account' => 'boolean',
            'is_crediting_account' => 'boolean',
        ];
    }
}
