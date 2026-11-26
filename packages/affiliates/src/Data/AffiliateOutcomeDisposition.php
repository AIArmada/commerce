<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

enum AffiliateOutcomeDisposition: string
{
    case Created = 'created';
    case Replayed = 'replayed';
    case Rejected = 'rejected';
    case NoAffiliate = 'no_affiliate';
}
