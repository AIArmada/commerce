<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

enum RewardSourceCorrectionStatus: string
{
    case Current = 'current';
    case Corrected = 'corrected';
    case Withdrawn = 'withdrawn';
}
