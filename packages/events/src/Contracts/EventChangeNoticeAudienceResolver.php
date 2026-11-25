<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventNotificationRecipientData;

interface EventChangeNoticeAudienceResolver
{
    /**
     * @return iterable<int, EventNotificationRecipientData>
     */
    public function resolve(string $updateId, string $audienceScope): iterable;
}
