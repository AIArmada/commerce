<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Address;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class AddressValidationPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMapPin;

    protected string $view = 'filament-customers::pages.address-validation';

    protected static ?string $slug = 'address-validation';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return 'Address Validation';
    }

    public function getTitle(): string
    {
        return 'Address Validation';
    }

    /**
     * @return array<int, array{id: string, customer_name: string, full_address: string, country: string, validated: bool}>
     */
    public function getUnvalidatedAddresses(): array
    {
        $query = Address::query()
            ->with('customer')
            ->where('is_verified', false);

        if ((bool) config('customers.features.owner.enabled', false)) {
            $query = OwnerUiScope::apply($query, includeGlobal: false);
        }

        return $query->limit(100)
            ->get()
            ->map(fn (Address $address) => [
                'id' => $address->id,
                'customer_name' => $address->customer?->full_name ?? 'Unknown',
                'full_address' => $address->full_address ?? "{$address->line1}, {$address->city}, {$address->postcode}",
                'country' => $address->country,
                'validated' => $address->is_verified,
            ])
            ->all();
    }

    public function validateAddress(string $addressId): void
    {
        $address = Address::find($addressId);

        if ($address === null) {
            Notification::make()
                ->title('Address not found')
                ->danger()
                ->send();

            return;
        }

        $address->update(['is_verified' => true]);

        Notification::make()
            ->title('Address validated successfully')
            ->success()
            ->send();
    }

    public function runBatchValidation(): void
    {
        Artisan::call('customers:validate-addresses');

        Notification::make()
            ->title('Batch address validation initiated')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('batch_validate')
                ->label('Run Batch Validation')
                ->icon('heroicon-o-check-badge')
                ->action('runBatchValidation')
                ->requiresConfirmation(),
        ];
    }
}
