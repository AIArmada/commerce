<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardEnrollmentStatus: string
{
    case Active = 'active';
    case Left = 'left';
}
