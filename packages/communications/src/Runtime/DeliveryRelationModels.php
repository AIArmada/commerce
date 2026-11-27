<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/**
 * Internal Eloquent relation and audit composition.
 *
 * @phpstan-type RelatedCommunicationDelivery \AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery
 * @phpstan-type RelatedCommunicationEvent \AIArmada\Communications\Features\Delivery\Models\CommunicationEvent
 */
interface DeliveryRelationModels
{
    /** @return class-string<RelatedCommunicationDelivery> */
    public function delivery(): string;

    /** @return class-string<RelatedCommunicationEvent> */
    public function event(): string;
}
