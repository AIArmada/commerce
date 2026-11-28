<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Contracts\OrderAdministration;
use AIArmada\Orders\Models\Order;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Order
    {
        $id = app(OrderAdministration::class)->createDraft($data['notes'] ?? null, $data['internal_notes'] ?? null);

        return Order::query()->findOrFail($id);
    }
}
