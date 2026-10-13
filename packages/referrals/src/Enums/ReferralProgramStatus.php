<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Enums;

enum ReferralProgramStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
