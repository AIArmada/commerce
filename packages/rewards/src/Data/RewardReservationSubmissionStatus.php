<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

enum RewardReservationSubmissionStatus: string
{
    case Sending = 'sending';
    case Submitted = 'submitted';
    case Unknown = 'unknown';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
