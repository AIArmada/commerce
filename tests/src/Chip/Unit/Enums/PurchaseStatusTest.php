<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\PurchaseStatus;

describe('PurchaseStatus Enum', function (): void {
    it('has exactly the documented CHIP purchase statuses', function (): void {
        $expectedStatuses = [
            'created', 'sent', 'viewed', 'pending_execute', 'pending_charge',
            'hold', 'pending_capture', 'pending_release', 'preauthorized',
            'paid', 'cleared', 'settled', 'pending_refund', 'refunded', 'error',
            'blocked', 'cancelled', 'overdue', 'expired', 'released', 'chargeback',
        ];

        $actualStatuses = array_map(fn ($case) => $case->value, PurchaseStatus::cases());

        expect($actualStatuses)->toHaveCount(21)
            ->and($actualStatuses)->toBe($expectedStatuses);
    });

    it('can be created from string value', function (): void {
        $status = PurchaseStatus::from('paid');

        expect($status)->toBeInstanceOf(PurchaseStatus::class)
            ->and($status->value)->toBe('paid');
    });

    it('provides human-readable labels', function (): void {
        expect(PurchaseStatus::PAID->label())->toBe('Paid')
            ->and(PurchaseStatus::PENDING_CAPTURE->label())->toBe('Pending Capture')
            ->and(PurchaseStatus::CHARGEBACK->label())->toBe('Chargeback');
    });

    it('correctly identifies successful statuses', function (): void {
        expect(PurchaseStatus::PAID->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::CLEARED->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::SETTLED->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::CREATED->isSuccessful())->toBeFalse()
            ->and(PurchaseStatus::ERROR->isSuccessful())->toBeFalse();
    });

    it('correctly identifies pending statuses', function (): void {
        expect(PurchaseStatus::CREATED->isPending())->toBeTrue()
            ->and(PurchaseStatus::SENT->isPending())->toBeTrue()
            ->and(PurchaseStatus::VIEWED->isPending())->toBeTrue()
            ->and(PurchaseStatus::HOLD->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_CAPTURE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_RELEASE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_CHARGE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_EXECUTE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PREAUTHORIZED->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_REFUND->isPending())->toBeTrue()
            ->and(PurchaseStatus::OVERDUE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PAID->isPending())->toBeFalse();
    });

    it('correctly identifies failed statuses', function (): void {
        expect(PurchaseStatus::CANCELLED->isFailed())->toBeTrue()
            ->and(PurchaseStatus::ERROR->isFailed())->toBeTrue()
            ->and(PurchaseStatus::EXPIRED->isFailed())->toBeTrue()
            ->and(PurchaseStatus::OVERDUE->isFailed())->toBeFalse()
            ->and(PurchaseStatus::PAID->isFailed())->toBeFalse();
    });

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
