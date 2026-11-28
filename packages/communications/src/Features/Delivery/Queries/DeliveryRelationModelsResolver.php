<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Queries;

use AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery;
use AIArmada\Communications\Features\Delivery\Models\CommunicationEvent;
use AIArmada\Communications\Runtime\DeliveryRelationModels;

final readonly class DeliveryRelationModelsResolver implements DeliveryRelationModels
{
    public function delivery(): string
    {
        return CommunicationDelivery::class;
    }

    public function event(): string
    {
        return CommunicationEvent::class;
    }
}
