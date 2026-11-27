<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/** Internal Eloquent relation and audit composition. */
interface DeliveryRelationModels
{
    /** @return class-string<\AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery> */
    public function delivery(): string;

    /** @return class-string<\AIArmada\Communications\Features\Delivery\Models\CommunicationEvent> */
    public function event(): string;
}
