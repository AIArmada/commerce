<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Concerns\AllowsCoupons;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use Akaunting\Money\Money;

class AllowsCouponsTest extends CashierChipTestCase
{
    public function test_with_coupon(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', ['price_123']);

        $result = $builder->withCoupon('COUPON_123');

        $this->assertSame($builder, $result);
        $this->assertEquals('COUPON_123', $builder->couponId);
    }

    public function test_with_coupon_null(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', ['price_123']);
        $builder->withCoupon('COUPON_123');

        $result = $builder->withCoupon(null);

        $this->assertNull($builder->couponId);
    }

    public function test_with_promotion_code(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', ['price_123']);

        $result = $builder->withPromotionCode('PROMO_123');

        $this->assertSame($builder, $result);
        $this->assertEquals('PROMO_123', $builder->promotionCodeId);
    }

    public function test_allow_promotion_codes(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', ['price_123']);

        $this->assertFalse($builder->allowPromotionCodes);

        $result = $builder->allowPromotionCodes();

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->allowPromotionCodes);
    }

    public function test_checkout_discounts_returns_null_without_coupon_or_promo(): void
    {
        $harness = new AllowsCouponsHarness;

        $this->assertNull($harness->exposeCheckoutDiscounts());
    }

    public function test_checkout_discounts_includes_coupon_and_promotion_code(): void
    {
        $voucher = VoucherData::fromArray([
            'id' => 'v_1',
            'code' => 'COUPON_123',
            'name' => 'Test Coupon',
            'type' => VoucherType::Percentage->value,
            'value' => 1000, // 10%
            'currency' => 'MYR',
            'status' => Active::class,
            'metadata' => ['duration' => 'once'],
        ]);

        $service = new FakeVoucherService([
            'COUPON_123' => $voucher,
        ]);

        $this->app->instance(VoucherService::class, $service);

        $harness = new AllowsCouponsHarness;
        $harness->withCoupon('COUPON_123');
        $harness->withPromotionCode('PROMO_123');

        $discounts = $harness->exposeCheckoutDiscounts();

        $this->assertSame([
            ['coupon' => 'COUPON_123'],
            ['promotion_code' => 'PROMO_123'],
        ], $discounts);
    }

    public function test_validate_coupon_for_checkout_throws_when_coupon_not_found(): void
    {
        $this->app->instance(VoucherService::class, new FakeVoucherService([]));

        $this->expectException(InvalidCoupon::class);

        (new AllowsCouponsHarness)->exposeValidateCouponForCheckout('MISSING');
    }

    public function test_validate_coupon_for_checkout_throws_when_coupon_inactive(): void
    {
        $voucher = VoucherData::fromArray([
            'id' => 'v_2',
            'code' => 'INACTIVE',
            'name' => 'Inactive',
            'type' => VoucherType::Fixed->value,
            'value' => 500,
            'currency' => 'MYR',
            'status' => Paused::class,
            'metadata' => ['duration' => 'once'],
        ]);

        $this->app->instance(VoucherService::class, new FakeVoucherService(['INACTIVE' => $voucher]));

        $this->expectException(InvalidCoupon::class);

        (new AllowsCouponsHarness)->exposeValidateCouponForCheckout('INACTIVE');
    }

    public function test_validate_coupon_for_checkout_throws_when_forever_amount_off(): void
    {
        $voucher = VoucherData::fromArray([
            'id' => 'v_3',
            'code' => 'FOREVER_OFF',
            'name' => 'Forever Amount Off',
            'type' => VoucherType::Fixed->value,
            'value' => 500,
            'currency' => 'MYR',
            'status' => Active::class,
            'metadata' => ['duration' => 'forever'],
        ]);

        $this->app->instance(VoucherService::class, new FakeVoucherService(['FOREVER_OFF' => $voucher]));

        $this->expectException(InvalidCoupon::class);

        (new AllowsCouponsHarness)->exposeValidateCouponForCheckout('FOREVER_OFF');
    }

    public function test_validate_coupon_for_subscription_application_throws_when_forever_amount_off(): void
    {
        $voucher = VoucherData::fromArray([
            'id' => 'v_4',
            'code' => 'FOREVER_OFF_SUB',
            'name' => 'Forever Amount Off',
            'type' => VoucherType::Fixed->value,
            'value' => 500,
            'currency' => 'MYR',
            'status' => Active::class,
            'metadata' => ['duration' => 'forever'],
        ]);

        $this->app->instance(VoucherService::class, new FakeVoucherService(['FOREVER_OFF_SUB' => $voucher]));

        $this->expectException(InvalidCoupon::class);

        (new AllowsCouponsHarness)->exposeValidateCouponForSubscriptionApplication('FOREVER_OFF_SUB');
    }

    public function test_calculate_coupon_discount_returns_zero_when_no_coupon_or_promo_set(): void
    {
        $this->app->instance(VoucherService::class, new FakeVoucherService([]));

        $harness = new AllowsCouponsHarness;

        $this->assertSame(0, $harness->exposeCalculateCouponDiscount(10_000));
    }

    public function test_calculate_coupon_discount_returns_discount_when_coupon_exists(): void
    {
        $voucher = VoucherData::fromArray([
            'id' => 'v_5',
            'code' => 'TENPCT',
            'name' => '10% Off',
            'type' => VoucherType::Percentage->value,
            'value' => 1000, // 10%
            'currency' => 'MYR',
            'status' => Active::class,
            'metadata' => ['duration' => 'once'],
        ]);

        $this->app->instance(VoucherService::class, new FakeVoucherService(['TENPCT' => $voucher]));

        $harness = new AllowsCouponsHarness;
        $harness->withCoupon('TENPCT');

        $this->assertSame(1000, $harness->exposeCalculateCouponDiscount(10_000));
    }

    public function test_record_coupon_usage_calls_voucher_service(): void
    {
        $service = new FakeVoucherService([]);
        $this->app->instance(VoucherService::class, $service);

        $this->app['config']->set('cashier-chip.currency', 'MYR');

        $harness = new AllowsCouponsHarness;
        $harness->exposeRecordCouponUsage('CODE123', 2500);

        $this->assertCount(1, $service->recordUsageCalls);

        $call = $service->recordUsageCalls[0];

        $this->assertSame('CODE123', $call['code']);
        $this->assertInstanceOf(Money::class, $call['discountAmount']);
        $this->assertSame('subscription', $call['channel']);
        $this->assertNull($call['metadata']);
        $this->assertNull($call['redeemedBy']);
        $this->assertSame(2500, $call['discountAmount']->getAmount());
        $this->assertSame('MYR', $call['discountAmount']->getCurrency()->getCurrency());
    }
}

final class AllowsCouponsHarness
{
    use AllowsCoupons;

    /** @return array<int, array<string, string>>|null */
    public function exposeCheckoutDiscounts(): ?array
    {
        return $this->checkoutDiscounts();
    }

    public function exposeValidateCouponForCheckout(string $couponId): void
    {
        $this->validateCouponForCheckout($couponId);
    }

    public function exposeValidateCouponForSubscriptionApplication(string $couponId): void
    {
        $this->validateCouponForSubscriptionApplication($couponId);
    }

    public function exposeCalculateCouponDiscount(int $amount): int
    {
        return $this->calculateCouponDiscount($amount);
    }

    public function exposeRecordCouponUsage(string $couponId, int $discountAmount, mixed $redeemedBy = null): void
    {
        $this->recordCouponUsage($couponId, $discountAmount, $redeemedBy);
    }
}

final class FakeVoucherService
{
    /** @var array<int, array{code: string, discountAmount: Money, channel: ?string, metadata: ?array, redeemedBy: mixed}> */
    public array $recordUsageCalls = [];

    /** @var array<string, VoucherData> */
    private array $vouchersByCode;

    /** @param array<string, VoucherData> $vouchersByCode */
    public function __construct(array $vouchersByCode)
    {
        $this->vouchersByCode = $vouchersByCode;
    }

    public function find(string $code): ?VoucherData
    {
        return $this->vouchersByCode[$code] ?? null;
    }

    /** @param array<string, mixed>|null $metadata */
    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        mixed $redeemedBy = null,
    ): void {
        $this->recordUsageCalls[] = [
            'code' => $code,
            'discountAmount' => $discountAmount,
            'channel' => $channel,
            'metadata' => $metadata,
            'redeemedBy' => $redeemedBy,
        ];
    }
}
