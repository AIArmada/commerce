<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PurchaseResource\Pages;

use AIArmada\FilamentChip\Resources\Pages\ReadOnlyListRecords;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use Override;

final class ListPurchases extends ReadOnlyListRecords
{
    protected static string $resource = PurchaseResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'CHIP Purchases';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Review every purchase ingested from CHIP Collect with rich, read-only insights.';
    }

    // Read-only list page does not expose create actions or widgets.
}
