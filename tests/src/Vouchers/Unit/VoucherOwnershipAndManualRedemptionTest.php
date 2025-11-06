<?php

declare(strict_types=1);

use AIArmada\Vouchers\Contracts\VoucherOwnerResolver;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Services\VoucherValidator;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TestOwner extends EloquentModel
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'test_owners';

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
    });

    config([
        'vouchers.owner.enabled' => false,
        'vouchers.owner.include_global' => true,
        'vouchers.owner.auto_assign_on_create' => true,
        'vouchers.redemption.manual_requires_flag' => true,
    ]);
});

it('redeems voucher manually when allowed', function (): void {
    $service = app(VoucherService::class);

    VoucherModel::create([
        'code' => 'MANUAL1',
        'name' => 'Manual Voucher',
        'type' => VoucherType::Fixed,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
    ]);

    $redeemer = TestOwner::create(['name' => 'Admin']);

    $service->redeemManually(
        code: 'MANUAL1',
        discountAmount: Money::USD(1000),
        reference: 'offline-001',
        metadata: ['source' => 'counter'],
        redeemedBy: $redeemer,
        notes: 'Redeemed at counter'
    );

    $usage = VoucherUsage::first();

    expect($usage)->not->toBeNull()
        ->and($usage->channel)->toBe(config('vouchers.redemption.channels.manual'))
        ->and($usage->metadata['reference'])->toBe('offline-001')
        ->and($usage->metadata['source'])->toBe('counter')
        ->and($usage->notes)->toBe('Redeemed at counter')
        ->and($usage->redeemedBy)->toBeInstanceOf(TestOwner::class)
        ->and($usage->redeemedBy->is($redeemer))->toBeTrue();

    $voucher = VoucherModel::where('code', 'MANUAL1')->first();

    expect($voucher)->not->toBeNull()
        ->and($voucher->times_used)->toBe(1);
});

it('rejects manual redemption when voucher disallows it', function (): void {
    $service = app(VoucherService::class);

    VoucherModel::create([
        'code' => 'BLOCKED',
        'name' => 'Blocked Voucher',
        'type' => VoucherType::Fixed,
        'value' => 5,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => false,
    ]);

    $service->redeemManually(
        code: 'BLOCKED',
        discountAmount: Money::USD(500)
    );
})->throws(ManualRedemptionNotAllowedException::class);

it('scopes vouchers to the resolved owner', function (): void {
    config(['vouchers.owner.enabled' => true]);

    $owner = TestOwner::create(['name' => 'Merchant A']);
    $otherOwner = TestOwner::create(['name' => 'Merchant B']);

    $this->app->forgetInstance(VoucherOwnerResolver::class);
    $this->app->singleton(VoucherOwnerResolver::class, function () use ($owner): VoucherOwnerResolver {
        return new class($owner) implements VoucherOwnerResolver
        {
            public function __construct(private TestOwner $owner) {}

            public function resolve(): ?EloquentModel
            {
                return $this->owner;
            }
        };
    });

    $this->app->forgetInstance(VoucherService::class);
    $this->app->forgetInstance(VoucherValidator::class);
    $this->app->forgetInstance('voucher');

    $service = app(VoucherService::class);

    $service->create([
        'code' => 'OWNED',
        'name' => 'Owned Voucher',
        'type' => VoucherType::Fixed,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
    ]);

    VoucherModel::create([
        'code' => 'OTHER',
        'name' => 'Other Owner Voucher',
        'type' => VoucherType::Fixed,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'owner_type' => $otherOwner->getMorphClass(),
        'owner_id' => $otherOwner->getKey(),
    ]);

    VoucherModel::create([
        'code' => 'GLOBAL',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 15,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
    ]);

    expect($service->find('OWNED'))->not->toBeNull()
        ->and($service->find('GLOBAL'))->not->toBeNull()
        ->and($service->find('OTHER'))->toBeNull();

    $owned = VoucherModel::where('code', 'OWNED')->first();

    expect($owned)->not->toBeNull()
        ->and($owned->owner_id)->toBe($owner->getKey())
        ->and($owned->owner_type)->toBe($owner->getMorphClass());
});
