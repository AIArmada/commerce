<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\ManualRedeemVoucherAction;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Services\VoucherService;
use Akaunting\Money\Money;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

function redeemableVoucher(array $overrides = []): Voucher
{
    return Voucher::query()->create(array_merge([
        'code' => 'REDEEM-' . mb_strtoupper(Str::random(6)),
        'name' => 'Redeemable',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'allows_manual_redemption' => true,
        'usage_limit' => null,
    ], $overrides));
}

function manualRedeemHandler(): Closure
{
    $action = ManualRedeemVoucherAction::make();

    return $action->getActionFunction();
}

it('rejects manual redemption once the voucher is no longer redeemable', function (): void {
    $voucher = redeemableVoucher(['allows_manual_redemption' => false]);
    $handler = manualRedeemHandler();

    expect(fn () => $handler($voucher, ['discount_amount' => '5.00']))
        ->toThrow(ValidationException::class);

    $exhausted = redeemableVoucher(['usage_limit' => 1]);
    VoucherUsage::create([
        'voucher_id' => $exhausted->id,
        'discount_amount' => 500,
        'currency' => 'MYR',
        'channel' => VoucherUsage::CHANNEL_MANUAL,
        'used_at' => now(),
    ]);

    expect(fn () => $handler($exhausted->fresh(), ['discount_amount' => '5.00']))
        ->toThrow(ValidationException::class);
});

it('rejects unparseable and sub-minimum manual amounts', function (): void {
    $voucher = redeemableVoucher();
    $handler = manualRedeemHandler();

    expect(fn () => $handler($voucher, ['discount_amount' => 'bogus']))
        ->toThrow(ValidationException::class);

    expect(fn () => $handler($voucher, ['discount_amount' => '0.00']))
        ->toThrow(ValidationException::class);
});

it('redeems with the parsed minor-unit amount', function (): void {
    $redeemed = [];
    $stub = new class($redeemed)
    {
        /** @var array<int, mixed> */
        public array $calls;

        public function __construct(array &$calls)
        {
            $this->calls = &$calls;
        }

        public function redeemManually(
            string $code,
            Money $discountAmount,
            ?string $reference = null,
            ?array $metadata = null,
            $redeemedBy = null,
            ?string $notes = null,
        ): object {
            $this->calls[] = [$code, $discountAmount];

            return new class
            {
                public function getKey(): string
                {
                    return 'usage-stub';
                }
            };
        }
    };

    app()->instance(VoucherService::class, $stub);

    $user = User::query()->create([
        'name' => 'Redeemer',
        'email' => 'redeemer-manual@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($user);

    $voucher = redeemableVoucher();
    $handler = manualRedeemHandler();
    $handler($voucher, ['discount_amount' => '12.50', 'reference' => 'POS-1', 'notes' => 'hi']);

    expect($stub->calls)->toHaveCount(1)
        ->and($stub->calls[0][0])->toBe($voucher->code)
        ->and((int) $stub->calls[0][1]->getAmount())->toBe(1250);
});
