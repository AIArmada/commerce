<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentOrders\Pages\FulfillmentQueue;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\FilamentVouchers\Resources\GiftCardResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('orders + vouchers filament navigation badges are owner-scoped (no cross-tenant aggregation)', function (): void {
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);

    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $ownerA = \App\Models\User::factory()->create();
    $ownerB = \App\Models\User::factory()->create();

    $createOrder = static function (string $status): void {
        Order::create([
            'status' => $status,
            'subtotal' => 10000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10000,
            'currency' => 'MYR',
        ]);
    };

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $createOrder): void {
        $createOrder(PendingPayment::class);
        $createOrder(Processing::class);

        Voucher::create([
            'code' => 'WELCOME-A',
            'name' => 'Welcome Voucher A',
            'type' => VoucherType::Fixed,
            'value' => 1000,
            'currency' => 'MYR',
            'status' => VoucherStatus::Active,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);

        GiftCard::create([
            'code' => 'GC-A-0001',
            'currency' => 'MYR',
            'initial_balance' => 5000,
            'current_balance' => 5000,
            'status' => GiftCardStatus::Active,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
        ]);

        $voucherA = Voucher::query()->where('code', 'WELCOME-A')->firstOrFail();

        VoucherWallet::create([
            'voucher_id' => $voucherA->id,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'is_claimed' => true,
            'is_redeemed' => false,
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerB, $createOrder): void {
        $createOrder(Processing::class);
        $createOrder(Processing::class);
        $createOrder(Processing::class);

        Voucher::create([
            'code' => 'WELCOME-B',
            'name' => 'Welcome Voucher B',
            'type' => VoucherType::Fixed,
            'value' => 2000,
            'currency' => 'MYR',
            'status' => VoucherStatus::Active,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        GiftCard::create([
            'code' => 'GC-B-0001',
            'currency' => 'MYR',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'status' => GiftCardStatus::Active,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        GiftCard::create([
            'code' => 'GC-B-0002',
            'currency' => 'MYR',
            'initial_balance' => 2500,
            'current_balance' => 2500,
            'status' => GiftCardStatus::Active,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        $voucherB = Voucher::query()->where('code', 'WELCOME-B')->firstOrFail();

        VoucherWallet::create([
            'voucher_id' => $voucherB->id,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'is_claimed' => true,
            'is_redeemed' => false,
        ]);

        VoucherWallet::create([
            'voucher_id' => $voucherB->id,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'is_claimed' => true,
            'is_redeemed' => false,
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(OrderResource::getNavigationBadge())->toBe('2');
        expect(FulfillmentQueue::getNavigationBadge())->toBe('1');

        expect(VoucherResource::getNavigationBadge())->toBe('1');
        expect(GiftCardResource::getNavigationBadge())->toBe('1');
        expect(VoucherWalletResource::getNavigationBadge())->toBe('1');
    });

    OwnerContext::withOwner($ownerB, function (): void {
        expect(OrderResource::getNavigationBadge())->toBe('3');
        expect(FulfillmentQueue::getNavigationBadge())->toBe('3');

        expect(VoucherResource::getNavigationBadge())->toBe('1');
        expect(GiftCardResource::getNavigationBadge())->toBe('2');
        expect(VoucherWalletResource::getNavigationBadge())->toBe('2');
    });
});
