<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
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

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-customers.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-customers.pages.navigation_sort.address_validation');

        return is_numeric($sort) ? (int) $sort : null;
    }

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
            ->whereNull('verified_at');

        if ((bool) config('customers.features.owner.enabled', false)) {
            $query = OwnerUiScope::apply($query, includeGlobal: false);
        }

        return $query->limit(100)
            ->get()
            ->map(fn (Address $address) => [
                'id' => $address->id,
                'customer_name' => $address->customer?->full_name ?? 'Unknown',
                'full_address' => $address->full_address ?? "{$address->line1}, {$address->city}, {$address->postcode}",
                'country' => $address->country ?? $address->country_code,
                'validated' => $address->getAttribute('verified_at') !== null,
            ])
            ->all();
    }

    public function validateAddress(string $addressId): void
    {
        $address = (bool) config('customers.features.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(Address::class, $addressId, includeGlobal: false)
            : Address::find($addressId);

        if ($address === null) {
            Notification::make()
                ->title('Address not found')
                ->danger()
                ->send();

            return;
        }

        $address->update(['verified_at' => now()]);

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
