<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\Pages;

use AIArmada\FilamentPersons\Resources\PersonResource;
use AIArmada\Persons\Actions\ReorderFeaturedPersonsAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListPersons extends ListRecords
{
    protected static string $resource = PersonResource::class;

    /** @param list<string> $order */
    public function reorderTable(array $order, int | string | null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        app(ReorderFeaturedPersonsAction::class)->handle(static::getResource()::getModel(), $order);
        $this->flushCachedTableRecords();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
