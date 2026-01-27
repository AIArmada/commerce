<?php

declare(strict_types=1);

use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;

describe('PaymentStatus Enum', function (): void {
    it('has all expected cases', function (): void {
        expect(PaymentStatus::cases())->toHaveCount(7)
            ->and(PaymentStatus::Pending->value)->toBe('pending')
            ->and(PaymentStatus::Processing->value)->toBe('processing')
            ->and(PaymentStatus::Completed->value)->toBe('completed')
            ->and(PaymentStatus::Failed->value)->toBe('failed')
            ->and(PaymentStatus::Refunded->value)->toBe('refunded')
            ->and(PaymentStatus::PartiallyRefunded->value)->toBe('partially_refunded')
            ->and(PaymentStatus::Cancelled->value)->toBe('cancelled');
    });

    it('identifies successful status', function (): void {
        expect(PaymentStatus::Completed->isSuccessful())->toBeTrue()
            ->and(PaymentStatus::Pending->isSuccessful())->toBeFalse()
            ->and(PaymentStatus::Failed->isSuccessful())->toBeFalse();
    });

    it('identifies final states', function (): void {
        expect(PaymentStatus::Completed->isFinal())->toBeTrue()
            ->and(PaymentStatus::Failed->isFinal())->toBeTrue()
            ->and(PaymentStatus::Refunded->isFinal())->toBeTrue()
            ->and(PaymentStatus::Cancelled->isFinal())->toBeTrue()
            ->and(PaymentStatus::Pending->isFinal())->toBeFalse()
            ->and(PaymentStatus::Processing->isFinal())->toBeFalse();
    });
});

describe('StepStatus Enum', function (): void {
    it('has all expected cases', function (): void {
        expect(StepStatus::cases())->toHaveCount(6)
            ->and(StepStatus::Pending->value)->toBe('pending')
            ->and(StepStatus::Skipped->value)->toBe('skipped')
            ->and(StepStatus::Processing->value)->toBe('processing')
            ->and(StepStatus::Completed->value)->toBe('completed')
            ->and(StepStatus::Failed->value)->toBe('failed')
            ->and(StepStatus::RolledBack->value)->toBe('rolled_back');
    });

    it('identifies complete states', function (): void {
        expect(StepStatus::Completed->isComplete())->toBeTrue()
            ->and(StepStatus::Skipped->isComplete())->toBeTrue()
            ->and(StepStatus::Pending->isComplete())->toBeFalse()
            ->and(StepStatus::Processing->isComplete())->toBeFalse()
            ->and(StepStatus::Failed->isComplete())->toBeFalse();
    });

    it('identifies states needing processing', function (): void {
        expect(StepStatus::Pending->needsProcessing())->toBeTrue()
            ->and(StepStatus::Completed->needsProcessing())->toBeFalse()
            ->and(StepStatus::Skipped->needsProcessing())->toBeFalse()
            ->and(StepStatus::Processing->needsProcessing())->toBeFalse();
    });
});
