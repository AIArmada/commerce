<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription as ChipSubscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem as ChipSubscriptionItem;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\TenantRecord;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages\ListInvoices;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\ListSubscriptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app()->register(CashierServiceProvider::class);

    config()->set('cashier.models.billable', ChipBillableUser::class);
    config()->set('cashier.gateways', [
        'chip' => [
            'driver' => 'chip',
            'label' => 'CHIP',
            'icon' => 'heroicon-o-cube',
            'color' => 'emerald',
            'dashboard_url' => 'https://gate.chip-in.asia',
        ],
    ]);
});

it('lists subscriptions across billables in the current owner scope', function (): void {
    if (! Schema::hasTable('cashier_chip_subscriptions')) {
        Schema::create('cashier_chip_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('billable');
            $table->nullableMorphs('owner');
            $table->string('type');
            $table->string('chip_id')->unique();
            $table->string('chip_status');
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('recurring_token')->nullable();
            $table->string('billing_interval')->default('month');
            $table->integer('billing_interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('cashier_chip_subscription_items')) {
        Schema::create('cashier_chip_subscription_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id');
            $table->nullableMorphs('owner');
            $table->string('chip_id')->unique();
            $table->string('chip_product')->nullable();
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('filament_cashier_tenant_records')) {
        Schema::create('filament_cashier_tenant_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    /** @var class-string<Model> $userModel */
    $userModel = ChipBillableUser::class;

    $admin = $userModel::query()->create([
        'name' => 'Subscription Admin',
        'email' => 'subscription-admin@example.com',
        'password' => bcrypt('secret'),
    ]);

    $other = $userModel::query()->create([
        'name' => 'Subscription Customer',
        'email' => 'subscription-customer@example.com',
        'password' => bcrypt('secret'),
    ]);

    $team = TenantRecord::query()->create([
        'user_id' => $admin->getKey(),
        'name' => 'Team A',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($team));

    Auth::guard()->setUser($admin);

    CashierChip::useCustomerModel($userModel);
    CashierChip::useSubscriptionModel(ChipSubscription::class);
    CashierChip::useSubscriptionItemModel(ChipSubscriptionItem::class);

    foreach ([$admin, $other] as $billable) {
        $id = (string) Str::uuid();

        ChipSubscription::query()->create([
            'id' => $id,
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => (string) $billable->getKey(),
            'type' => 'default',
            'chip_id' => 'sub_' . $id,
            'chip_status' => SubscriptionStatus::Active,
            'chip_price' => 'price_basic_monthly',
            'quantity' => 1,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'trial_ends_at' => null,
            'next_billing_at' => Carbon::now()->addMonth(),
            'ends_at' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    $records = app(ListSubscriptions::class)->getTableRecords();

    expect($records)->toHaveCount(2);
    expect($records->pluck('userId')->all())->toContain(
        (string) $admin->getKey(),
        (string) $other->getKey(),
    );
});

it('delegates unified invoice listing to the configured gateway client', function (): void {
    $user = ChipBillableUser::query()->create([
        'name' => 'Invoice List User',
        'email' => 'invoice-list@example.com',
        'password' => bcrypt('secret'),
    ]);

    Auth::guard()->setUser($user);

    $gateway = Mockery::mock(GatewayContract::class);
    $gateway->shouldReceive('invoices')->once()->with($user, ['limit' => 100])->andReturn(collect());
    Cashier::shouldReceive('supportedGateways')->once()->andReturn(['chip']);
    Cashier::shouldReceive('gateway')->once()->with('chip')->andReturn($gateway);

    expect(app(ListInvoices::class)->getTableRecords())->toBeEmpty();
});
