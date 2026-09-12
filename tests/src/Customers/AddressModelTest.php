<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Actions\MergeCustomers;
use AIArmada\Customers\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Services\CustomerResolver;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

describe('Customer canonical addresses', function (): void {
    beforeEach(function (): void {
        $this->customer = Customer::create([
            'first_name' => 'Address',
            'last_name' => 'Test',
            'status' => CustomerStatus::Active,
        ]);
    });

    it('resolves the shared addressing table and relation', function (): void {
        $address = new Address;

        expect($address->getTable())->toBeString()
            ->and($this->customer->addresses())->toBeInstanceOf(MorphToMany::class);
    });

    it('attaches addresses with a typed primary pivot', function (): void {
        $address = Address::create([
            'line1' => '123 Main St',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $this->customer->attachAddress($address, type: 'shipping', isPrimary: true);

        $persisted = $this->customer->fresh()?->primaryAddress('shipping');

        expect($persisted?->is($address))->toBeTrue()
            ->and($persisted?->pivot?->type)->toBe('shipping')
            ->and($persisted?->pivot?->is_primary)->toBeTrue();
    });

    it('keeps exactly one primary address for each type', function (): void {
        $first = Address::create([
            'line1' => '123 First St',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);
        $second = Address::create([
            'line1' => '456 Second St',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $this->customer->attachAddress($first, type: 'billing', isPrimary: true);
        $this->customer->attachAddress($second, type: 'billing', isPrimary: true);

        $billing = $this->customer->fresh()?->addressesOfType('billing');

        expect($billing?->where('pivot.is_primary', true))->toHaveCount(1)
            ->and($this->customer->fresh()?->primaryAddress('billing')?->is($second))->toBeTrue();
    });

    it('changes only the requested customer primary type', function (): void {
        $billing = Address::create([
            'line1' => '1 Billing Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);
        $shipping = Address::create([
            'line1' => '2 Shipping Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);
        $replacement = Address::create([
            'line1' => '3 Replacement Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $this->customer->attachAddress($billing, type: 'billing', isPrimary: true);
        $this->customer->attachAddress($shipping, type: 'shipping', isPrimary: true);
        $this->customer->attachAddress($replacement, type: 'billing');

        app(SetDefaultCustomerAddress::class)->execute($this->customer, $replacement, 'billing');

        $freshCustomer = $this->customer->fresh();

        expect($freshCustomer?->primaryAddress('billing')?->is($replacement))->toBeTrue()
            ->and($freshCustomer?->primaryAddress('shipping')?->is($shipping))->toBeTrue();
    });

    it('attaches a persisted address when making it primary', function (): void {
        $address = Address::create([
            'line1' => '123 Main St',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        app(SetDefaultCustomerAddress::class)->execute($this->customer, $address, 'shipping');

        expect($this->customer->fresh()?->primaryAddress('shipping')?->is($address))->toBeTrue();
    });

    it('rejects unsaved addresses in the default action', function (): void {
        $address = new Address([
            'line1' => 'Unsaved Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        expect(fn (): mixed => app(SetDefaultCustomerAddress::class)->execute($this->customer, $address, 'shipping'))
            ->toThrow(LogicException::class, 'Only persisted addresses can be made default.');
    });

    it('hydrates canonical addresses from resolver payloads', function (): void {
        $resolver = app(CustomerResolver::class);

        $customer = $resolver->resolve(
            user: null,
            sessionCustomer: null,
            billingData: [
                'email' => 'resolver-' . uniqid() . '@example.com',
                'name' => 'Resolver Test',
                'phone' => '+60123456789',
            ],
            shippingData: [
                'name' => 'Resolver Test',
                'company' => 'Resolver Co',
                'line1' => '123 Resolver St',
                'line2' => 'Suite 200',
                'city' => 'Kuala Lumpur',
                'state' => 'WP',
                'postcode' => '50000',
                'country' => 'MY',
            ],
        );

        $address = $customer?->addresses()
            ->wherePivot('type', 'shipping')
            ->first();

        expect($address)->not->toBeNull()
            ->and($address?->line1)->toBe('123 Resolver St')
            ->and($address?->line2)->toBe('Suite 200')
            ->and($address?->metadata['recipient_name'])->toBe('Resolver Test')
            ->and($address?->metadata['company'])->toBe('Resolver Co')
            ->and($customer?->primaryAddress('shipping')?->is($address))->toBeTrue()
            ->and($customer?->resolvePhone())->toBe('+60123456789');
    });

    it('detaches customer address pivots without deleting reusable address rows', function (): void {
        $address = Address::create([
            'line1' => '123 Main St',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);
        $this->customer->attachAddress($address, type: 'shipping', isPrimary: true);

        $this->customer->delete();

        expect(Addressable::query()->where('address_id', $address->id)->exists())->toBeFalse()
            ->and(OwnerContext::withOwner(
                $address->owner,
                fn (): bool => Address::query()->whereKey($address->id)->exists(),
            ))->toBeTrue();
    });

    it('keeps the surviving customer primary when merging address attachments', function (): void {
        $source = Customer::create([
            'first_name' => 'Source',
            'last_name' => 'Customer',
            'status' => CustomerStatus::Active,
        ]);
        $targetAddress = Address::create([
            'line1' => '123 Target Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);
        $sourceAddress = Address::create([
            'line1' => '456 Source Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $this->customer->attachAddress($targetAddress, type: 'billing', isPrimary: true);
        $source->attachAddress($sourceAddress, type: 'billing', isPrimary: true);

        app(MergeCustomers::class)->execute($this->customer, $source);

        expect($this->customer->fresh()?->primaryAddress('billing')?->is($targetAddress))->toBeTrue()
            ->and($this->customer->fresh()?->addresses()->count())->toBe(2)
            ->and(Customer::query()->whereKey($source->getKey())->exists())->toBeFalse();
    });
});
