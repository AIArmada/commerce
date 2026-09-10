<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum TicketAccessType: string
{
    use HasLabelOptions;

    case General = 'general';
    case GeneralAdmission = 'general_admission';
    case ReservedSeating = 'reserved_seating';
    case Vip = 'vip';
    case Complimentary = 'complimentary';
    case Entry = 'entry';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::GeneralAdmission => 'General Admission',
            self::ReservedSeating => 'Reserved Seating',
            self::Vip => 'VIP',
            self::Complimentary => 'Complimentary',
            self::Entry => 'Entry',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::General, self::GeneralAdmission => 'gray',
            self::ReservedSeating => 'info',
            self::Vip => 'warning',
            self::Complimentary => 'success',
            self::Entry => 'primary',
        };
    }
}
