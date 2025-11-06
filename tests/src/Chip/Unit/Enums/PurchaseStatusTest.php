<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\PurchaseStatus;

describe('PurchaseStatus Enum', function (): void {
    it('has all 26 official CHIP purchase statuses', function (): void {
        $expectedStatuses = [
            'created', 'sent', 'viewed', 'pending_execute', 'pending_charge',
            'hold', 'pending_capture', 'pending_release', 'preauthorized',
            'paid', 'paid_authorized', 'recurring_successful', 'cleared', 'settled',
            'pending_refund', 'refunded', 'error', 'blocked', 'cancelled',
            'overdue', 'expired', 'released', 'chargeback',
            'attempted_capture', 'attempted_refund', 'attempted_recurring',
        ];

        $actualStatuses = array_map(fn ($case) => $case->value, PurchaseStatus::cases());

        expect($actualStatuses)->toHaveCount(26)
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
            ->and(PurchaseStatus::RECURRING_SUCCESSFUL->label())->toBe('Recurring Successful');
    });

    it('correctly identifies successful statuses', function (): void {
        expect(PurchaseStatus::PAID->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::PAID_AUTHORIZED->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::RECURRING_SUCCESSFUL->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::CLEARED->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::SETTLED->isSuccessful())->toBeTrue()
            ->and(PurchaseStatus::CREATED->isSuccessful())->toBeFalse()
            ->and(PurchaseStatus::ERROR->isSuccessful())->toBeFalse();
    });

    it('correctly identifies pending statuses', function (): void {
        expect(PurchaseStatus::CREATED->isPending())->toBeTrue()
            ->and(PurchaseStatus::SENT->isPending())->toBeTrue()
            ->and(PurchaseStatus::VIEWED->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_CAPTURE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_RELEASE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_CHARGE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PENDING_EXECUTE->isPending())->toBeTrue()
            ->and(PurchaseStatus::PREAUTHORIZED->isPending())->toBeTrue()
            ->and(PurchaseStatus::PAID->isPending())->toBeFalse();
    });

    it('correctly identifies failed statuses', function (): void {
        expect(PurchaseStatus::CANCELLED->isFailed())->toBeTrue()
            ->and(PurchaseStatus::ERROR->isFailed())->toBeTrue()
            ->and(PurchaseStatus::EXPIRED->isFailed())->toBeTrue()
            ->and(PurchaseStatus::OVERDUE->isFailed())->toBeTrue()
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
            ->and(PurchaseStatus::PAID_AUTHORIZED->canBeCaptured())->toBeFalse()
            ->and(PurchaseStatus::PAID->canBeCaptured())->toBeFalse()
            ->and(PurchaseStatus::CREATED->canBeCaptured())->toBeFalse();
    });

    it('correctly identifies which purchases can be released', function (): void {
        expect(PurchaseStatus::HOLD->canBeReleased())->toBeTrue()
            ->and(PurchaseStatus::PREAUTHORIZED->canBeReleased())->toBeFalse()
            ->and(PurchaseStatus::PAID_AUTHORIZED->canBeReleased())->toBeFalse()
            ->and(PurchaseStatus::PAID->canBeReleased())->toBeFalse();
    });

    it('correctly identifies which purchases can be refunded', function (): void {
        expect(PurchaseStatus::PAID->canBeRefunded())->toBeTrue()
            ->and(PurchaseStatus::CLEARED->canBeRefunded())->toBeTrue()
            ->and(PurchaseStatus::SETTLED->canBeRefunded())->toBeTrue()
            ->and(PurchaseStatus::CREATED->canBeRefunded())->toBeFalse()
            ->and(PurchaseStatus::REFUNDED->canBeRefunded())->toBeFalse();
    });

    it('has all new official statuses that were previously missing', function (): void {
        // These 12 statuses were missing before the accuracy cleanup
        $previouslyMissingStatuses = [
            'paid_authorized',
            'recurring_successful',
            'cleared',
            'settled',
            'chargeback',
            'pending_capture',
            'pending_release',
            'pending_charge',
            'attempted_capture',
            'attempted_refund',
            'attempted_recurring',
            'sent', // Was also missing
        ];

        foreach ($previouslyMissingStatuses as $status) {
            expect(PurchaseStatus::tryFrom($status))
                ->not->toBeNull()
                ->toBeInstanceOf(PurchaseStatus::class);
        }
    });

    it('does not have fake undocumented statuses', function (): void {
        // These fake statuses were removed during accuracy cleanup
        $fakeStatuses = ['pending', 'pending_verification'];

        foreach ($fakeStatuses as $status) {
            expect(PurchaseStatus::tryFrom($status))->toBeNull();
        }
    });
});
