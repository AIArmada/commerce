<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;

/** Internal Eloquent relation and audit composition. */
interface DeliveryRelationModels
{
    /** @return class-string<CommunicationDelivery> */
    public function delivery(): string;

    /** @return class-string<CommunicationEvent> */
    public function event(): string;
}
