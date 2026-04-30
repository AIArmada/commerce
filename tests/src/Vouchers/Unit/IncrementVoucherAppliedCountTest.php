<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('does not increment malformed owner tuple rows when handling global voucher applications', function (): void {
    config()->set('vouchers.tracking.track_applications', true);

    $table = (new Voucher)->getTable();

    DB::table($table)->insert([
        'id' => (string) Str::uuid(),
        'owner_type' => 'broken-owner-type',
        'owner_id' => null,
        'code' => 'MALFORMED-GLOBAL',
        'name' => 'Malformed Global Voucher Row',
        'description' => null,
        'type' => VoucherType::Fixed->value,
        'value' => 100,
        'value_config' => null,
        'credit_destination' => null,
        'credit_delay_hours' => 0,
        'currency' => 'MYR',
        'min_cart_value' => null,
        'max_discount' => null,
        'usage_limit' => null,
        'usage_limit_per_user' => null,
        'applied_count' => 0,
        'allows_manual_redemption' => false,
        'starts_at' => null,
        'expires_at' => null,
        'status' => Active::class,
        'target_definition' => null,
        'metadata' => null,
        'stacking_rules' => null,
        'exclusion_groups' => null,
        'stacking_priority' => 100,
        'affiliate_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $event = new VoucherApplied(
        new Cart(new InMemoryStorage, 'listener-cart-' . Str::uuid()->toString()),
        VoucherData::fromArray([
            'id' => (string) Str::uuid(),
            'code' => 'MALFORMED-GLOBAL',
            'name' => 'Global Voucher Event',
            'type' => VoucherType::Fixed,
            'value' => 100,
            'currency' => 'MYR',
            'status' => Active::class,
            'owner_id' => null,
            'owner_type' => null,
        ])
    );

    (new IncrementVoucherAppliedCount)->handle($event);

    $appliedCount = DB::table($table)
        ->where('code', 'MALFORMED-GLOBAL')
        ->value('applied_count');

    expect((int) $appliedCount)->toBe(0);
});
