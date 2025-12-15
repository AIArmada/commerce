<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\ChargeStatus;
use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Enums\WebhookEventType;

describe('RecurringInterval enum', function (): void {
    it('has correct values', function (): void {
        expect(RecurringInterval::Daily->value)->toBe('daily');
        expect(RecurringInterval::Weekly->value)->toBe('weekly');
        expect(RecurringInterval::Monthly->value)->toBe('monthly');
        expect(RecurringInterval::Yearly->value)->toBe('yearly');
    });

    it('returns correct labels', function (): void {
        expect(RecurringInterval::Daily->label())->toBe('Daily');
        expect(RecurringInterval::Weekly->label())->toBe('Weekly');
        expect(RecurringInterval::Monthly->label())->toBe('Monthly');
        expect(RecurringInterval::Yearly->label())->toBe('Yearly');
    });

    it('converts to days correctly', function (): void {
        expect(RecurringInterval::Daily->toDays())->toBe(1);
        expect(RecurringInterval::Daily->toDays(5))->toBe(5);
        expect(RecurringInterval::Weekly->toDays())->toBe(7);
        expect(RecurringInterval::Weekly->toDays(2))->toBe(14);
        expect(RecurringInterval::Monthly->toDays())->toBe(30);
        expect(RecurringInterval::Monthly->toDays(3))->toBe(90);
        expect(RecurringInterval::Yearly->toDays())->toBe(365);
        expect(RecurringInterval::Yearly->toDays(2))->toBe(730);
    });
});

describe('RecurringStatus enum', function (): void {
    it('has correct values', function (): void {
        expect(RecurringStatus::Active->value)->toBe('active');
        expect(RecurringStatus::Paused->value)->toBe('paused');
        expect(RecurringStatus::Cancelled->value)->toBe('cancelled');
        expect(RecurringStatus::Failed->value)->toBe('failed');
    });

    it('returns correct labels', function (): void {
        expect(RecurringStatus::Active->label())->toBe('Active');
        expect(RecurringStatus::Paused->label())->toBe('Paused');
        expect(RecurringStatus::Cancelled->label())->toBe('Cancelled');
        expect(RecurringStatus::Failed->label())->toBe('Failed');
    });

    it('returns correct colors', function (): void {
        expect(RecurringStatus::Active->color())->toBe('success');
        expect(RecurringStatus::Paused->color())->toBe('warning');
        expect(RecurringStatus::Cancelled->color())->toBe('gray');
        expect(RecurringStatus::Failed->color())->toBe('danger');
    });

    it('returns correct icons', function (): void {
        expect(RecurringStatus::Active->icon())->toBe('heroicon-o-play');
        expect(RecurringStatus::Paused->icon())->toBe('heroicon-o-pause');
        expect(RecurringStatus::Cancelled->icon())->toBe('heroicon-o-x-circle');
        expect(RecurringStatus::Failed->icon())->toBe('heroicon-o-exclamation-triangle');
    });

    it('correctly checks isActive', function (): void {
        expect(RecurringStatus::Active->isActive())->toBeTrue();
        expect(RecurringStatus::Paused->isActive())->toBeFalse();
        expect(RecurringStatus::Cancelled->isActive())->toBeFalse();
        expect(RecurringStatus::Failed->isActive())->toBeFalse();
    });

    it('correctly checks isFinal', function (): void {
        expect(RecurringStatus::Active->isFinal())->toBeFalse();
        expect(RecurringStatus::Paused->isFinal())->toBeFalse();
        expect(RecurringStatus::Cancelled->isFinal())->toBeTrue();
        expect(RecurringStatus::Failed->isFinal())->toBeTrue();
    });
});

describe('ChargeStatus enum', function (): void {
    it('has correct values', function (): void {
        expect(ChargeStatus::Pending->value)->toBe('pending');
        expect(ChargeStatus::Success->value)->toBe('success');
        expect(ChargeStatus::Failed->value)->toBe('failed');
    });

    it('returns correct labels', function (): void {
        expect(ChargeStatus::Pending->label())->toBe('Pending');
        expect(ChargeStatus::Success->label())->toBe('Success');
        expect(ChargeStatus::Failed->label())->toBe('Failed');
    });

    it('returns correct colors', function (): void {
        expect(ChargeStatus::Pending->color())->toBe('warning');
        expect(ChargeStatus::Success->color())->toBe('success');
        expect(ChargeStatus::Failed->color())->toBe('danger');
    });

    it('returns correct icons', function (): void {
        expect(ChargeStatus::Pending->icon())->toBe('heroicon-o-clock');
        expect(ChargeStatus::Success->icon())->toBe('heroicon-o-check-circle');
        expect(ChargeStatus::Failed->icon())->toBe('heroicon-o-x-circle');
    });
});

describe('WebhookEventType enum', function (): void {
    it('has correct purchase lifecycle values', function (): void {
        expect(WebhookEventType::PurchaseCreated->value)->toBe('purchase.created');
        expect(WebhookEventType::PurchasePaid->value)->toBe('purchase.paid');
        expect(WebhookEventType::PurchasePaymentFailure->value)->toBe('purchase.payment_failure');
        expect(WebhookEventType::PurchaseCancelled->value)->toBe('purchase.cancelled');
    });

    it('has correct payout values', function (): void {
        expect(WebhookEventType::PayoutPending->value)->toBe('payout.pending');
        expect(WebhookEventType::PayoutFailed->value)->toBe('payout.failed');
        expect(WebhookEventType::PayoutSuccess->value)->toBe('payout.success');
    });

    it('can create from string', function (): void {
        expect(WebhookEventType::fromString('purchase.paid'))->toBe(WebhookEventType::PurchasePaid);
        expect(WebhookEventType::fromString('payout.success'))->toBe(WebhookEventType::PayoutSuccess);
        expect(WebhookEventType::fromString('unknown.event'))->toBeNull();
    });

    it('returns correct labels', function (): void {
        expect(WebhookEventType::PurchaseCreated->label())->toBe('Purchase Created');
        expect(WebhookEventType::PurchasePaid->label())->toBe('Purchase Paid');
        expect(WebhookEventType::PaymentRefunded->label())->toBe('Payment Refunded');
        expect(WebhookEventType::PayoutSuccess->label())->toBe('Payout Successful');
    });

    it('correctly identifies purchase events', function (): void {
        expect(WebhookEventType::PurchasePaid->isPurchaseEvent())->toBeTrue();
        expect(WebhookEventType::PurchaseCancelled->isPurchaseEvent())->toBeTrue();
        expect(WebhookEventType::PayoutSuccess->isPurchaseEvent())->toBeFalse();
    });

    it('correctly identifies payout events', function (): void {
        expect(WebhookEventType::PayoutSuccess->isPayoutEvent())->toBeTrue();
        expect(WebhookEventType::PayoutFailed->isPayoutEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isPayoutEvent())->toBeFalse();
    });

    it('correctly identifies billing events', function (): void {
        expect(WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled->isBillingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isBillingEvent())->toBeFalse();
    });

    it('correctly identifies payment events', function (): void {
        expect(WebhookEventType::PaymentRefunded->isPaymentEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isPaymentEvent())->toBeFalse();
    });

    it('correctly identifies pending events', function (): void {
        expect(WebhookEventType::PurchasePendingExecute->isPendingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePendingCharge->isPendingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePendingCapture->isPendingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isPendingEvent())->toBeFalse();
    });

    it('correctly identifies success events', function (): void {
        expect(WebhookEventType::PurchasePaid->isSuccessEvent())->toBeTrue();
        expect(WebhookEventType::PurchaseCaptured->isSuccessEvent())->toBeTrue();
        expect(WebhookEventType::PayoutSuccess->isSuccessEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaymentFailure->isSuccessEvent())->toBeFalse();
    });

    it('correctly identifies failure events', function (): void {
        expect(WebhookEventType::PurchasePaymentFailure->isFailureEvent())->toBeTrue();
        expect(WebhookEventType::PurchaseSubscriptionChargeFailure->isFailureEvent())->toBeTrue();
        expect(WebhookEventType::PayoutFailed->isFailureEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isFailureEvent())->toBeFalse();
    });

    it('returns correct event class names', function (): void {
        expect(WebhookEventType::PurchasePaid->eventClass())->toBe('AIArmada\\Chip\\Events\\PurchasePaid');
        expect(WebhookEventType::PayoutSuccess->eventClass())->toBe('AIArmada\\Chip\\Events\\PayoutSuccess');
        expect(WebhookEventType::PaymentRefunded->eventClass())->toBe('AIArmada\\Chip\\Events\\PaymentRefunded');
        expect(WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled->eventClass())->toBe('AIArmada\\Chip\\Events\\BillingCancelled');
    });
});
