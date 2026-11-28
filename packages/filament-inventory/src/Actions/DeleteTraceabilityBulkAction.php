<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Actions;

use AIArmada\Inventory\Features\Traceability\Actions\DeleteTraceabilityRecord;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

final class DeleteTraceabilityBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizeIndividualRecords('delete');
        $this->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records): void {
            $reported = false;

            foreach ($records as $record) {
                try {
                    if (! ($record instanceof InventoryBatch || $record instanceof InventorySerial)
                        || ! app(DeleteTraceabilityRecord::class)->execute($record)) {
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
