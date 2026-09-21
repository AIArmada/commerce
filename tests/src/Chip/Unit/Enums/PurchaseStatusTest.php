<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\PurchaseStatus;

describe('PurchaseStatus Enum', function (): void {
    it('correctly identifies which purchases can be cancelled', function (): void {
        expect(PurchaseStatus::CREATED->canBeCancelled())->toBeTrue()
            ->and(PurchaseStatus::SENT->canBeCancelled())->toBeTrue()
            ->and(PurchaseStatus::VIEWED->canBeCancelled())->toBeTrue()
            ->and(PurchaseStatus::PAID->canBeCancelled())->toBeFalse()
            ->and(PurchaseStatus::CANCELLED->canBeCancelled())->toBeFalse();
    });

    it('correctly identifies which purchases can be captured', function (): void {
        expect(PurchaseStatus::HOLD->canBeCaptured())->toBeTrue()
            ->and(PurchaseStatus::PREAUTHORIZED->canBeCaptured())->toBeFalse()
            ->and(PurchaseStatus::PAID->canBeCaptured())->toBeFalse()
            ->and(PurchaseStatus::CREATED->canBeCaptured())->toBeFalse();
    });

    it('correctly identifies which purchases can be released', function (): void {
        expect(PurchaseStatus::HOLD->canBeReleased())->toBeTrue()
            ->and(PurchaseStatus::PREAUTHORIZED->canBeReleased())->toBeFalse()
            ->and(PurchaseStatus::PAID->canBeReleased())->toBeFalse();
    });

    it('correctly identifies which purchases can be refunded', function (): void {
        expect(PurchaseStatus::PAID->canBeRefunded())->toBeTrue()
            ->and(PurchaseStatus::CLEARED->canBeRefunded())->toBeTrue()
            ->and(PurchaseStatus::SETTLED->canBeRefunded())->toBeTrue()
            ->and(PurchaseStatus::CREATED->canBeRefunded())->toBeFalse()
            ->and(PurchaseStatus::REFUNDED->canBeRefunded())->toBeFalse();
    });

    it('does not have non-API derived statuses', function (): void {
        $fakeStatuses = [
            'pending',
            'pending_verification',
            'captured',
            'partially_refunded',
            'attempted_capture',
        ];

        foreach ($fakeStatuses as $status) {
            expect(PurchaseStatus::tryFrom($status))->toBeNull();
        }
    });
});
