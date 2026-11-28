<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Contracts\OrderAdministration;
use AIArmada\Orders\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Order, 404);
        app(OrderAdministration::class)->updateNotes((string) $record->getKey(), $data);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(function (Order $record): bool {
                    app(OrderAdministration::class)->deleteDrafts([(string) $record->getKey()]);

                    return true;
                }),
        ];
    }
}
