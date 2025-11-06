<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $status
 */
final class ChipBankAccount extends ChipModel
{
    public $timestamps = true;

    public function statusColor(): string
    {
        return match ($this->status) {
            'approved', 'active' => 'success',
            'pending', 'verifying' => 'warning',
            'rejected', 'disabled' => 'danger',
            default => 'gray',
        };
    }

    public function statusLabel(): string
    {
        return (string) str($this->status)->headline();
    }

    public function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === 'active' || $this->status === 'approved');
    }

    protected static function tableSuffix(): string
    {
        return 'bank_accounts';
    }

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
