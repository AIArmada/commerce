<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\States\Active;
use Illuminate\Support\Facades\Event;

uses(SignalsTestCase::class);

it('records a cart item added signal for the owner-scoped property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Cart Other Owner',
        'email' => 'cart-other-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Cart Property',
        'slug' => 'owner-a-cart-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Cart Property',
            'slug' => 'owner-b-cart-property',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $storage = (new InMemoryStorage)->withOwner($owner);
    $cart = new Cart($storage, 'cart-owner-a', events: null);
    $item = new CartItem('sku-1', 'Signals T-Shirt', 4900, 2);

    Event::dispatch(new ItemAdded($item, $cart));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('cart.item.added')
        ->and($event->event_category)->toBe('cart')
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->signal_session_id)->not->toBeNull()
        ->and($event->properties)->toMatchArray([
            'cart_identifier' => 'cart-owner-a',
            'cart_instance' => 'default',
            'item_id' => 'sku-1',
            'item_name' => 'Signals T-Shirt',
            'quantity' => 2,
            'unit_price_minor' => 4900,
            'line_total_minor' => 9800,
        ]);
});

it('records a voucher applied signal for the owner-scoped property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Voucher Other Owner',
        'email' => 'voucher-other-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Voucher Property',
        'slug' => 'owner-a-voucher-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Voucher Property',
            'slug' => 'owner-b-voucher-property',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $storage = (new InMemoryStorage)->withOwner($owner);
    $cart = new Cart($storage, 'cart-owner-voucher', events: null);

    $voucher = VoucherData::fromArray([
        'id' => 'voucher-1',
        'code' => 'WELCOME10',
        'name' => 'Welcome Discount',
        'type' => VoucherType::Fixed->value,
        'value' => 1000,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    Event::dispatch(new VoucherApplied($cart, $voucher));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('voucher.applied')
        ->and($event->event_category)->toBe('promotion')
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->signal_session_id)->not->toBeNull()
        ->and($event->properties)->toMatchArray([
            'cart_identifier' => 'cart-owner-voucher',
            'cart_instance' => 'default',
            'voucher_id' => 'voucher-1',
            'voucher_code' => 'WELCOME10',
            'voucher_name' => 'Welcome Discount',
            'voucher_type' => VoucherType::Fixed->value,
            'voucher_value' => 1000,
        ]);
});
