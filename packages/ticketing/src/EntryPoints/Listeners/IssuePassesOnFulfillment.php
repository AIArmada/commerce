<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\EntryPoints\Listeners;

use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Ticketing\Actions\IssuePassesForOrderAction;

final readonly class IssuePassesOnFulfillment
{
    public function __construct(private IssuePassesForOrderAction $issuePasses) {}

    public function handle(OrderFulfillmentRequired $event): void
    {
        $this->issuePasses->handle($event->order);
    }
}
