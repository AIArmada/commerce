<?php

declare(strict_types=1);

use AIArmada\Chip\Actions\Purchases\CancelPurchase;
use AIArmada\Chip\Actions\Purchases\CapturePurchase;
use AIArmada\Chip\Actions\Purchases\ChargePurchase;
use AIArmada\Chip\Actions\Purchases\CreatePurchase;
use AIArmada\Chip\Actions\Purchases\RefundPurchase;
use Lorisleiva\Actions\Concerns\AsAction;

describe('Purchase actions', function (): void {
    it('exist and use the AsAction trait', function (string $actionClass): void {
        expect(class_exists($actionClass))->toBeTrue();
        expect(class_uses($actionClass))->toContain(AsAction::class);
    })->with([
        'create' => [CreatePurchase::class],
        'cancel' => [CancelPurchase::class],
        'capture' => [CapturePurchase::class],
        'charge' => [ChargePurchase::class],
        'refund' => [RefundPurchase::class],
    ]);
});
