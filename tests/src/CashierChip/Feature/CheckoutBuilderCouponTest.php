<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Checkout;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Active;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;

uses(CashierChipTestCase::class);

final class CheckoutBuilderCouponVoucherService extends VoucherService
{
    public function __construct(private readonly VoucherData $voucher) {}

    public function find(string $code): ?VoucherData
    {
        return $code === $this->voucher->code ? $this->voucher : null;
    }

    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null,
    ): void {}
}

it('distributes a checkout coupon across product lines and does not mutate the builder', function (): void {
    $voucher = VoucherData::fromArray([
        'id' => 'v_checkout_lines',
        'code' => 'LINES1500',
        'name' => 'Lines 1500',
        'type' => VoucherType::Fixed->value,
        'value' => 1500,
        'currency' => 'MYR',
        'status' => Active::class,
        'metadata' => [],
    ]);

    $this->app->instance(VoucherService::class, new CheckoutBuilderCouponVoucherService($voucher));

    $user = $this->createUser(['chip_id' => 'cli_checkout_lines']);

    $builder = Checkout::customer($user)
        ->currency('MYR')
        ->products([
            ['name' => 'Setup', 'price' => 500, 'quantity' => 1],
            ['name' => 'Monthly', 'price' => 1500, 'quantity' => 1],
        ])
        ->withCoupon('LINES1500');

    $first = $builder->create(2000);
    $second = $builder->create(2000);

    $purchases = $this->fakeChip->getFakeClient()->getPurchases();

    $firstProducts = $purchases[$first->id()]['purchase']['products'];
    $secondProducts = $purchases[$second->id()]['purchase']['products'];

    expect($purchases[$first->id()]['purchase']['total'])->toBe(500)
        ->and($purchases[$second->id()]['purchase']['total'])->toBe(500)
        ->and($firstProducts[0]['discount'])->toBe(500)
        ->and($firstProducts[1]['discount'])->toBe(1000)
        ->and($secondProducts[0]['discount'])->toBe(500)
        ->and($secondProducts[1]['discount'])->toBe(1000);
});
