<?php

declare(strict_types=1);

use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;
use Carbon\CarbonImmutable;

it('marks vouchers with no remaining uses as limit reached', function (): void {
    $widget = new AppliedVoucherBadgesWidget;

    $voucher = new class
    {
        public int $usage_limit = 100;

        public function getRemainingUses(): int
        {
            return 0;
        }
    };

    $method = new ReflectionMethod(AppliedVoucherBadgesWidget::class, 'determineVoucherStatus');

    $status = $method->invoke($widget, $voucher);

    expect($status)->toBe('limit_reached');
});

it('keeps fallback active status when remaining uses method is unavailable', function (): void {
    $widget = new AppliedVoucherBadgesWidget;

    $voucher = new class
    {
        public int $usage_limit = 100;
    };

    $method = new ReflectionMethod(AppliedVoucherBadgesWidget::class, 'determineVoucherStatus');

    $status = $method->invoke($widget, $voucher);

    expect($status)->toBe('active');
});

it('still prioritizes expiry status checks', function (): void {
    $widget = new AppliedVoucherBadgesWidget;

    $voucher = new class
    {
        public int $usage_limit = 100;

        public CarbonImmutable $end_date;

        public function __construct()
        {
            $this->end_date = CarbonImmutable::now()->subDay();
        }

        public function getRemainingUses(): int
        {
            return 0;
        }
    };

    $method = new ReflectionMethod(AppliedVoucherBadgesWidget::class, 'determineVoucherStatus');

    $status = $method->invoke($widget, $voucher);

    expect($status)->toBe('expired');
});
