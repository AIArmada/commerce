<?php

declare(strict_types=1);

use AIArmada\Cart\Cart as LiveCart;
use AIArmada\Cart\Data\CartSnapshotData;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot as Cart;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Exceptions\VoucherException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\CartVoucherApplicator;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;
use AIArmada\Vouchers\Support\CartWithVouchers;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('covers cart-related widgets and voucher suggestions', function (): void {
    config()->set('vouchers.owner.enabled', false);

    $cart = Cart::query()->create([
        'instance' => 'default',
        'identifier' => 'cart-1',
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $live = new LiveCart(new InMemoryStorage, $cart->identifier);
    new CartWithVouchers($live);
    $applied = new VoucherCondition(VoucherData::fromArray([
        'id' => 'applied',
        'code' => 'APPLIED',
        'name' => 'Applied voucher',
        'type' => 'fixed',
        'value' => 500,
        'currency' => 'USD',
        'status' => 'active',
    ]));
    $live->registerDynamicCondition($applied->toCartCondition(), null, $applied->getRuleFactoryKey(), $applied->getRuleFactoryContext());

    $manager = Mockery::mock(CartInstanceManager::class);
    $manager->shouldReceive('resolveForSnapshot')->andReturn($live);
    app()->instance(CartInstanceManager::class, $manager);

    $voucher = Voucher::query()->create([
        'code' => 'SAVE10',
        'name' => 'Save 10%',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'currency' => 'USD',
        'status' => 'active',
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $tableWidget = app(AppliedVouchersWidget::class);
    $tableWidget->record = $cart;

    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    expect($tableWidget->table(Table::make($livewire)))->toBeInstanceOf(Table::class);

    $queryMethod = new ReflectionMethod(AppliedVouchersWidget::class, 'getAppliedVouchersQuery');
    $collection = $queryMethod->invoke($tableWidget);

    expect($collection)->toBeIterable()
        ->and($collection->pluck('code')->all())->toContain('APPLIED');

    $quick = app(QuickApplyVoucherWidget::class);
    $quick->voucherCode = 'CODE';

    expect($quick->form(Schema::make($quick)))->toBeInstanceOf(Schema::class);

    // Non-cart branch
    $quick->record = null;
    $quick->applyVoucher();

    // Cart branch (VoucherException)
    $applicator = Mockery::mock(CartVoucherApplicator::class);
    $applicator->shouldReceive('handle')->once()->with($live, 'FAIL')->andThrow(new VoucherException('Cannot apply'));
    app()->instance(CartVoucherApplicator::class, $applicator);

    $quick->record = $cart;
    $quick->voucherCode = 'FAIL';
    $quick->applyVoucher();

    // Cart branch (Throwable)
    app()->instance(CartInstanceManager::class, new class
    {
        public function resolveForSnapshot(CartSnapshotData $snapshot): object
        {
            throw new RuntimeException('Boom');
        }
    });

    $quick->voucherCode = 'ERR';
    $quick->applyVoucher();

    // Cart branch (success)
    app()->instance(CartInstanceManager::class, $manager);
    $applicator = Mockery::mock(CartVoucherApplicator::class);
    $condition = new VoucherCondition(VoucherSnapshot::fromModel($voucher));
    $applicator->shouldReceive('handle')->once()->with($live, 'OK')->andReturn($condition);
    $applicator->shouldReceive('handle')->once()->with($live, $voucher->code, null, 100, (string) $voucher->getKey())->andReturn($condition);
    app()->instance(CartVoucherApplicator::class, $applicator);

    $quick->voucherCode = 'OK';
    $quick->applyVoucher();
    expect($quick->voucherCode)->toBe('');

    $suggestions = app(VoucherSuggestionsWidget::class);
    $suggestions->record = $cart;

    $eligible = $suggestions->getEligibleVouchers();
    expect($eligible)->not->toBeEmpty();

    $calc = new ReflectionMethod(VoucherSuggestionsWidget::class, 'calculatePotentialSavings');

    $recommend = new ReflectionMethod(VoucherSuggestionsWidget::class, 'generateRecommendation');

    $freeShipping = Voucher::make([
        'type' => VoucherType::FreeShipping,
        'value' => 0,
        'currency' => 'USD',
    ]);

    expect($calc->invoke($suggestions, $freeShipping, 10000))->toBe(0);
    expect($recommend->invoke($suggestions, $freeShipping, 10000, 0))->toContain('free shipping');

    $bigSavings = Voucher::make([
        'type' => VoucherType::Fixed,
        'value' => 5000,
        'currency' => 'USD',
    ]);

    expect($recommend->invoke($suggestions, $bigSavings, 10000, 5000))->toContain('Save');

    $expiring = Voucher::make([
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'USD',
        'expires_at' => now()->addDays(2),
    ]);

    expect($recommend->invoke($suggestions, $expiring, 10000, 100))->toContain('Expires');

    $limited = Voucher::query()->create([
        'code' => 'LIMITED',
        'name' => 'Limited',
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'USD',
        'status' => 'active',
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
        'usage_limit' => 5,
    ]);

    expect($recommend->invoke($suggestions, $limited, 10000, 100))->toContain('uses left');

    $suggestions->applySuggestion((string) $voucher->getKey());

    $applicator = Mockery::mock(CartVoucherApplicator::class);
    $applicator->shouldReceive('handle')->once()->with($live, $voucher->code, null, 100, (string) $voucher->getKey())->andThrow(new RuntimeException('Boom'));
    app()->instance(CartVoucherApplicator::class, $applicator);

    $suggestions->applySuggestion((string) $voucher->getKey());
});

it('prevents voucher suggestions widget cross-tenant cart access when owner scoping enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-voucher-suggestions@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-voucher-suggestions@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $cartOwnedByB = Cart::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
        'instance' => 'default',
        'identifier' => 'cart-owned-by-b',
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $voucher = Voucher::query()->create([
        'code' => 'OWNER-A-ONLY',
        'name' => 'Owner A Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => 'active',
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $voucher->assignOwner($ownerA)->save();

    $manager = Mockery::mock(CartInstanceManager::class);
    $manager->shouldNotReceive('resolveForSnapshot');
    app()->instance(CartInstanceManager::class, $manager);

    $suggestions = app(VoucherSuggestionsWidget::class);
    $suggestions->record = $cartOwnedByB;

    expect($suggestions->getEligibleVouchers())->toBeEmpty();
    $suggestions->applySuggestion((string) $voucher->getKey());

});

it('honors the exact owner scope when global voucher suggestions are suppressed', function (): void {
    config()->set('cart.owner.enabled', false);
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Exact Owner',
        'email' => 'exact-owner-voucher-suggestions@example.com',
        'password' => 'secret',
    ]);

    $global = OwnerContext::withOwner(null, fn () => Voucher::query()->create([
        'code' => 'GLOBAL-SUGGESTION',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => 'active',
    ]));

    OwnerContext::withOwner($owner, function () use ($owner, $global): void {
        $cart = Cart::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'instance' => 'default',
            'identifier' => 'exact-owner-suggestion',
            'currency' => 'USD',
            'subtotal' => 10000,
            'total' => 10000,
        ]);

        $manager = Mockery::mock(CartInstanceManager::class);
        $manager->shouldReceive('resolveForSnapshot')->once()
            ->andReturn(new LiveCart(new InMemoryStorage, $cart->identifier));
        app()->instance(CartInstanceManager::class, $manager);

        $applicator = Mockery::mock(CartVoucherApplicator::class);
        $applicator->shouldNotReceive('handle');
        app()->instance(CartVoucherApplicator::class, $applicator);

        $widget = app(VoucherSuggestionsWidget::class);
        $widget->record = $cart;

        OwnerScopeOverride::withoutIncludeGlobal(function () use ($widget, $global): void {
            expect($widget->getEligibleVouchers())->toBeEmpty();
            $widget->applySuggestion((string) $global->getKey());
        });
    });
});
