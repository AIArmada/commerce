<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

final class DeleteAuthzDefinitions
{
    /** @param callable(Model): bool $delete */
    public static function bulk(callable $delete): DeleteBulkAction
    {
        return DeleteBulkAction::make()->using(static function (DeleteBulkAction $action, Collection | LazyCollection $records) use ($delete): void {
            $reported = false;

            foreach ($records as $record) {
                try {
                    if (! $delete($record)) {
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
