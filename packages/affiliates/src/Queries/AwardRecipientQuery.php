<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Queries;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Affiliates\Models\Affiliate;

final class AwardRecipientQuery
{
    public static function fromAffiliate(Affiliate $affiliate): AwardRecipient
    {
        return new AwardRecipient(
            id: (string) $affiliate->getKey(),
            morphType: $affiliate->getMorphClass(),
            connectionName: (string) $affiliate->getConnection()->getName(),
            ownerType: $affiliate->owner_type,
            ownerId: $affiliate->owner_id,
        );
    }
}
