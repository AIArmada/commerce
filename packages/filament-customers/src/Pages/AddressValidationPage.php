<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\Addressing\Actions\ValidateAddressAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Customer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Stateless validation report: lists customer addresses that currently fail
 * their country validation profile. Nothing is stamped or persisted; fixing
 * the address data removes it from this page.
 */
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
     * @return array<int, array{id: string, customer_name: string, full_address: string, country: string, violations: array<string, string>}>
     */
    public function getInvalidAddresses(): array
    {
        $validator = app(ValidateAddressAction::class);

        return $this->customerAddressesQuery()
            ->limit(100)
            ->get()
            ->map(function (Address $address) use ($validator): ?array {
                $violations = $validator->execute(AddressData::from($address->attributesToArray()));

                if ($violations === []) {
                    return null;
                }

                return [
                    'id' => $address->id,
                    'customer_name' => $this->resolveCustomerName($address),
                    'full_address' => $address->formatted_address
                        ?? implode(', ', array_filter([$address->line1, $address->city, $address->postcode])),
                    'country' => $address->country ?? $address->country_code,
                    'violations' => $violations,
                ];
            })
            ->filter()
            ->values()
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

    /**
     * @return Builder<Address>
     */
    private function customerAddressesQuery(): Builder
    {
        $query = ModelResolver::addressClass()::query()
            ->with(['addressableLinks.addressable'])
            ->whereHas('addressableLinks', function (Builder $query): void {
                $query->where('addressable_type', (new Customer)->getMorphClass());
            })
            ->orderByDesc('updated_at');

        return OwnerUiScope::apply($query, includeGlobal: false);
    }
}
