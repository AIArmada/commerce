<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum RankQualificationReason: string
{
    case Qualified = 'qualified';
    case Demoted = 'demoted';
    case Manual = 'manual';
    case Initial = 'initial';

    public function label(): string
    {
        return match ($this) {
            self::Qualified => 'Qualified',
            self::Demoted => 'Demoted',
            self::Manual => 'Manual Assignment',
            self::Initial => 'Initial Rank',
        };
    }
}
