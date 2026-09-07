<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Actions\IssueVouchersFromPromotion;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Model;

it('issues one-time vouchers linked to the source promotion', function (): void {
    $owner = new class extends Model
    {
        public $incrementing = false;

        protected $keyType = 'string';
    };
    $owner->id = 'store-launch-vouchers';
    $owner->setTable('stores');

    $promotion = OwnerContext::withOwner($owner, function () use ($owner): Promotion {
        $promotion = new Promotion([
            'name' => 'Launch 10',
            'code' => 'LAUNCH10',
            'type' => PromotionType::Percentage,
            'discount_value' => 10,
            'is_active' => true,
            'conditions' => [
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ],
        ]);

        $promotion->assignOwner($owner);
        $promotion->save();

        return $promotion;
    });

    $issued = IssueVouchersFromPromotion::run($promotion, count: 2);

    expect($issued)->toHaveCount(2)
        ->and($issued->first())->toBeInstanceOf(VoucherData::class);

    /** @var VoucherData $firstVoucher */
    $firstVoucher = $issued->first();
    $storedVoucher = Voucher::query()->where('code', $firstVoucher->code)->firstOrFail();

    expect($firstVoucher->type)->toBe(VoucherType::Percentage)
        ->and($firstVoucher->promotionId)->toBe($promotion->id)
        ->and($firstVoucher->usageLimit)->toBe(1)
        ->and($storedVoucher->promotion_id)->toBe($promotion->id)
        ->and($storedVoucher->owner_type)->toBe($promotion->owner_type)
        ->and((string) $storedVoucher->owner_id)->toBe((string) $promotion->owner_id)
        ->and($storedVoucher->metadata)->toBeNull()
        ->and($storedVoucher->value)->toBe(1000)
        ->and($storedVoucher->target_definition)->toBe([
            'targeting' => [
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ],
        ]);
});

it('requires explicit global context before issuing vouchers from a global promotion', function (): void {
    $promotion = OwnerContext::withOwner(null, fn (): Promotion => Promotion::create([
        'name' => 'Global Promo',
        'code' => 'GLOBAL10',
        'type' => PromotionType::Fixed,
        'discount_value' => 1500,
        'is_active' => true,
    ]));

    expect(fn () => IssueVouchersFromPromotion::run($promotion))
        ->toThrow(NoCurrentOwnerException::class);

    $issued = OwnerContext::withOwner(null, fn () => IssueVouchersFromPromotion::run($promotion));

    /** @var VoucherData $voucher */
    $voucher = $issued->sole();
    $storedVoucher = Voucher::query()->where('code', $voucher->code)->firstOrFail();

    expect($voucher->promotionId)->toBe($promotion->id)
        ->and($storedVoucher->owner_type)->toBeNull()
        ->and($storedVoucher->owner_id)->toBeNull();
});
