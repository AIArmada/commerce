<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardPreviewDecision: string
{
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case Hold = 'hold';
}
