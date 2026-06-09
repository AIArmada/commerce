<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Open = 'open';
    case Draft = 'draft';
    case Void = 'void';
    case Uncollectible = 'uncollectible';

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Open => 'warning',
            self::Draft => 'gray',
            self::Void => 'danger',
            self::Uncollectible => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Paid => 'heroicon-o-check-circle',
            self::Open => 'heroicon-o-clock',
            self::Draft => 'heroicon-o-pencil',
            self::Void => 'heroicon-o-x-circle',
            self::Uncollectible => 'heroicon-o-exclamation-circle',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('filament-cashier::invoices.status.paid'),
            self::Open => __('filament-cashier::invoices.status.open'),
            self::Draft => __('filament-cashier::invoices.status.draft'),
            self::Void => __('filament-cashier::invoices.status.void'),
            self::Uncollectible => __('filament-cashier::invoices.status.uncollectible'),
        };
    }
}
