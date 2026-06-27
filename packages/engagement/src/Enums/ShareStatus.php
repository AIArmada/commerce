<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum ShareStatus: string
{
    case Created = 'created';
    case Shared = 'shared';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Failed = 'failed';
}
