<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Customer;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
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
            ->with(['addressableLinks.addressable'])
            ->whereNull('validated_at')
            ->whereHas('addressableLinks', function (Builder $query): void {
                $query->where('addressable_type', (new Customer)->getMorphClass());
            });

        $query = OwnerUiScope::apply($query, includeGlobal: false);

        return $query->limit(100)
            ->get()
            ->map(fn (Address $address): array => [
                'id' => $address->id,
                'customer_name' => $this->resolveCustomerName($address),
                'full_address' => $address->formatted_address
                    ?? implode(', ', array_filter([$address->line1, $address->city, $address->postcode])),
                'country' => $address->country ?? $address->country_code,
                'validated' => $address->validated_at !== null,
            ])
            ->all();
    }

    private function resolveCustomerName(Address $address): string
    {
        foreach ($address->addressableLinks as $link) {
            if (! $link instanceof Addressable || ! $link->addressable instanceof Customer) {
                continue;
            }

            return $link->addressable->full_name;
        }

        return 'Unknown';
    }

    public function validateAddress(string $addressId): void
    {
        /** @var Address $address */
        $address = OwnerWriteGuard::findOrFailForOwner(Address::class, $addressId, includeGlobal: false);

        $address->update([
            'validation_status' => 'verified',
            'validated_at' => CarbonImmutable::now(),
        ]);

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
