<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum TaxDocumentStatus: string
{
    case Pending = 'pending';
    case PendingInfo = 'pending_info';
    case Generated = 'generated';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PendingInfo => 'Pending Info',
            self::Generated => 'Generated',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PendingInfo => 'warning',
            self::Generated => 'info',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
