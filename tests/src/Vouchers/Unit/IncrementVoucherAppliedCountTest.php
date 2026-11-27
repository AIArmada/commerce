<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Features\Lifecycle\EntryPoints\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('tracks applications only for the exact voucher and valid owner tuple', function (?string $rowOwnerType, bool $matchingIdentity, ?string $eventOwnerType, int $expectedCount): void {
    config()->set('vouchers.tracking.track_applications', true);

    $table = (new Voucher)->getTable();

    $voucherId = (string) Str::uuid();

    DB::table($table)->insert([
        'id' => $voucherId,
        'owner_type' => $rowOwnerType,
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
        'status' => 'active',
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
            'id' => $matchingIdentity ? $voucherId : (string) Str::uuid(),
            'code' => 'MALFORMED-GLOBAL',
            'name' => 'Global Voucher Event',
            'type' => VoucherType::Fixed,
            'value' => 100,
            'currency' => 'MYR',
            'status' => 'active',
            'owner_id' => null,
            'owner_type' => $eventOwnerType,
        ])
    );

    (app(IncrementVoucherAppliedCount::class))->handle($event);

    $appliedCount = DB::table($table)
        ->where('code', 'MALFORMED-GLOBAL')
        ->value('applied_count');

    expect((int) $appliedCount)->toBe($expectedCount);
})->with([
    'malformed persisted owner tuple' => ['broken-owner-type', true, null, 0],
    'reused code with different voucher id' => [null, false, null, 0],
    'malformed event owner tuple' => [null, true, 'broken-owner-type', 0],
    'exact global voucher' => [null, true, null, 1],
]);
