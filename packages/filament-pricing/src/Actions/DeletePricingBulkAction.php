<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Actions;

use AIArmada\Pricing\Actions\DeletePriceList;
use AIArmada\Pricing\Actions\SavePrice;
use AIArmada\Pricing\Actions\SavePriceTier;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

final class DeletePricingBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizeIndividualRecords('delete');
        $this->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
            $reported = false;

            foreach ($records as $record) {
                try {
                    $deleted = match (true) {
                        $record instanceof PriceList => app(DeletePriceList::class)->execute($record),
                        $record instanceof Price => app(SavePrice::class)->delete($record),
                        $record instanceof PriceTier => app(SavePriceTier::class)->delete($record),
                        default => false,
                    };

                    if (! $deleted) {
                        $action->reportBulkProcessingFailure();
                    }
                } catch (Throwable $exception) {
                    $action->reportBulkProcessingFailure();

                    if (! $reported) {
                        report($exception);
                        $reported = true;
                    }
                }
            }
        });
    }
}
