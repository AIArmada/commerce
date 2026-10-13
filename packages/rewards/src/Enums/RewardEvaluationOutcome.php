<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardEvaluationOutcome: string
{
    case Awarded = 'awarded';
    case Held = 'held';
    case Rejected = 'rejected';
}
