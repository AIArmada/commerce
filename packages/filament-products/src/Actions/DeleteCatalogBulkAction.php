<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Actions;

use AIArmada\Products\Actions\DeleteCatalogRecord;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection as CatalogCollection;
use AIArmada\Products\Models\Product;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

final class DeleteCatalogBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizeIndividualRecords('delete');
        $this->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
            $reported = false;

            foreach ($records as $record) {
                try {
                    if (! ($record instanceof Attribute || $record instanceof AttributeGroup || $record instanceof AttributeSet
                        || $record instanceof Category || $record instanceof CatalogCollection || $record instanceof Product)
                        || ! app(DeleteCatalogRecord::class)->execute($record)) {
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
