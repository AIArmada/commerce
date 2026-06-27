<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

enum DocPaymentStatus: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Voided = 'voided';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
        };
    }
}
