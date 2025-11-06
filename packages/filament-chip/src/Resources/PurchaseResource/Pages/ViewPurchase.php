<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PurchaseResource\Pages;

use AIArmada\FilamentChip\Resources\Pages\ReadOnlyViewRecord;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewPurchase extends ReadOnlyViewRecord
{
    protected static string $resource = PurchaseResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Purchase %s', $record->reference ?? $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedRectangleStack;
    }
}
