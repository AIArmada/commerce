<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;

use AIArmada\Customers\Features\Identity\Actions\DeleteCustomer;
use AIArmada\Customers\Features\Identity\Actions\SaveCustomerDetails;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Customer) {
            throw new InvalidArgumentException('Invalid customer record.');
        }

        return app(SaveCustomerDetails::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->using(fn (Customer $record): bool => app(DeleteCustomer::class)->execute($record)),
        ];
    }
}
