<?php

declare(strict_types=1);

namespace AIArmada\Promotions\EntryPoints\Listeners;

use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Promotions\Actions\RecordPromotionFulfillmentUsage;

final readonly class MarkPromotionAsUsedOnFulfillment
{
    public function __construct(private RecordPromotionFulfillmentUsage $recordPromotionFulfillmentUsage) {}

    public function handle(OrderFulfillmentRequired $event): void
    {
        $this->recordPromotionFulfillmentUsage->handle($event->order, $event->owner_type, $event->owner_id);
    }
}
