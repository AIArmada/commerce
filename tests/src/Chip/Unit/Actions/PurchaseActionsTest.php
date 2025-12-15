<?php

declare(strict_types=1);

use AIArmada\Chip\Actions\Purchases\CancelPurchase;
use AIArmada\Chip\Actions\Purchases\CapturePurchase;
use AIArmada\Chip\Actions\Purchases\ChargePurchase;
use AIArmada\Chip\Actions\Purchases\CreatePurchase;
use AIArmada\Chip\Actions\Purchases\RefundPurchase;
use Lorisleiva\Actions\Concerns\AsAction;

describe('CreatePurchase action', function () {
    it('exists and uses AsAction trait', function () {
        expect(class_exists(CreatePurchase::class))->toBeTrue();
        expect(class_uses(CreatePurchase::class))->toContain(AsAction::class);
    });
});

describe('CancelPurchase action', function () {
    it('exists and uses AsAction trait', function () {
        expect(class_exists(CancelPurchase::class))->toBeTrue();
        expect(class_uses(CancelPurchase::class))->toContain(AsAction::class);
    });
});

describe('CapturePurchase action', function () {
    it('exists and uses AsAction trait', function () {
        expect(class_exists(CapturePurchase::class))->toBeTrue();
        expect(class_uses(CapturePurchase::class))->toContain(AsAction::class);
    });
});

describe('ChargePurchase action', function () {
    it('exists and uses AsAction trait', function () {
        expect(class_exists(ChargePurchase::class))->toBeTrue();
        expect(class_uses(ChargePurchase::class))->toContain(AsAction::class);
    });
});

describe('RefundPurchase action', function () {
    it('exists and uses AsAction trait', function () {
        expect(class_exists(RefundPurchase::class))->toBeTrue();
        expect(class_uses(RefundPurchase::class))->toContain(AsAction::class);
    });
});
