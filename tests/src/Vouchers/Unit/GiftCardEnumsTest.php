<?php

declare(strict_types=1);

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;

describe('GiftCardType Enum', function (): void {
    it('has correct labels for all types', function (): void {
        expect(GiftCardType::Standard->label())->toBe('Standard')
            ->and(GiftCardType::OpenValue->label())->toBe('Open Value')
            ->and(GiftCardType::Promotional->label())->toBe('Promotional')
            ->and(GiftCardType::Reward->label())->toBe('Reward')
            ->and(GiftCardType::Corporate->label())->toBe('Corporate');
    });

    it('has descriptions for all types', function (): void {
        expect(GiftCardType::Standard->description())->toContain('Fixed denomination')
            ->and(GiftCardType::OpenValue->description())->toContain('chooses')
            ->and(GiftCardType::Promotional->description())->toContain('marketing')
            ->and(GiftCardType::Reward->description())->toContain('loyalty')
            ->and(GiftCardType::Corporate->description())->toContain('B2B');
    });

    it('correctly identifies types requiring purchase', function (): void {
        expect(GiftCardType::Standard->requiresPurchase())->toBeTrue()
            ->and(GiftCardType::OpenValue->requiresPurchase())->toBeTrue()
            ->and(GiftCardType::Corporate->requiresPurchase())->toBeTrue()
            ->and(GiftCardType::Promotional->requiresPurchase())->toBeFalse()
            ->and(GiftCardType::Reward->requiresPurchase())->toBeFalse();
    });

    it('correctly identifies types that can be topped up', function (): void {
        expect(GiftCardType::Standard->canBeToppedup())->toBeTrue()
            ->and(GiftCardType::OpenValue->canBeToppedup())->toBeTrue()
            ->and(GiftCardType::Corporate->canBeToppedup())->toBeTrue()
            ->and(GiftCardType::Promotional->canBeToppedup())->toBeFalse()
            ->and(GiftCardType::Reward->canBeToppedup())->toBeFalse();
    });

    it('correctly identifies types that can be transferred', function (): void {
        expect(GiftCardType::Standard->canBeTransferred())->toBeTrue()
            ->and(GiftCardType::OpenValue->canBeTransferred())->toBeTrue()
            ->and(GiftCardType::Corporate->canBeTransferred())->toBeTrue()
            ->and(GiftCardType::Promotional->canBeTransferred())->toBeFalse()
            ->and(GiftCardType::Reward->canBeTransferred())->toBeFalse();
    });

    it('has colors for UI display', function (): void {
        expect(GiftCardType::Standard->color())->toBe('primary')
            ->and(GiftCardType::OpenValue->color())->toBe('info')
            ->and(GiftCardType::Promotional->color())->toBe('success')
            ->and(GiftCardType::Reward->color())->toBe('warning')
            ->and(GiftCardType::Corporate->color())->toBe('gray');
    });

    it('provides options for form selects', function (): void {
        $options = GiftCardType::options();

        expect($options)->toBeArray()
            ->and($options)->toHaveCount(5)
            ->and($options['standard'])->toBe('Standard')
            ->and($options['promotional'])->toBe('Promotional');
    });
});

describe('GiftCardStatus Enum', function (): void {
    it('has correct labels for all statuses', function (): void {
        expect(GiftCardStatus::Inactive->label())->toBe('Inactive')
            ->and(GiftCardStatus::Active->label())->toBe('Active')
            ->and(GiftCardStatus::Suspended->label())->toBe('Suspended')
            ->and(GiftCardStatus::Exhausted->label())->toBe('Exhausted')
            ->and(GiftCardStatus::Expired->label())->toBe('Expired')
            ->and(GiftCardStatus::Cancelled->label())->toBe('Cancelled');
    });

    it('has descriptions for all statuses', function (): void {
        expect(GiftCardStatus::Inactive->description())->toContain('not been activated')
            ->and(GiftCardStatus::Active->description())->toContain('active and can be used')
            ->and(GiftCardStatus::Suspended->description())->toContain('temporarily suspended')
            ->and(GiftCardStatus::Exhausted->description())->toContain('fully used')
            ->and(GiftCardStatus::Expired->description())->toContain('expiration')
            ->and(GiftCardStatus::Cancelled->description())->toContain('cancelled');
    });

    it('correctly identifies statuses that can redeem', function (): void {
        expect(GiftCardStatus::Active->canRedeem())->toBeTrue()
            ->and(GiftCardStatus::Inactive->canRedeem())->toBeFalse()
            ->and(GiftCardStatus::Suspended->canRedeem())->toBeFalse()
            ->and(GiftCardStatus::Exhausted->canRedeem())->toBeFalse()
            ->and(GiftCardStatus::Expired->canRedeem())->toBeFalse()
            ->and(GiftCardStatus::Cancelled->canRedeem())->toBeFalse();
    });

    it('correctly identifies statuses that can top up', function (): void {
        expect(GiftCardStatus::Active->canTopUp())->toBeTrue()
            ->and(GiftCardStatus::Exhausted->canTopUp())->toBeTrue()
            ->and(GiftCardStatus::Inactive->canTopUp())->toBeFalse()
            ->and(GiftCardStatus::Suspended->canTopUp())->toBeFalse()
            ->and(GiftCardStatus::Expired->canTopUp())->toBeFalse()
            ->and(GiftCardStatus::Cancelled->canTopUp())->toBeFalse();
    });

    it('correctly identifies statuses that can transfer', function (): void {
        expect(GiftCardStatus::Active->canTransfer())->toBeTrue()
            ->and(GiftCardStatus::Inactive->canTransfer())->toBeTrue()
            ->and(GiftCardStatus::Suspended->canTransfer())->toBeFalse()
            ->and(GiftCardStatus::Exhausted->canTransfer())->toBeFalse()
            ->and(GiftCardStatus::Expired->canTransfer())->toBeFalse()
            ->and(GiftCardStatus::Cancelled->canTransfer())->toBeFalse();
    });

    it('correctly identifies terminal statuses', function (): void {
        expect(GiftCardStatus::Expired->isTerminal())->toBeTrue()
            ->and(GiftCardStatus::Cancelled->isTerminal())->toBeTrue()
            ->and(GiftCardStatus::Active->isTerminal())->toBeFalse()
            ->and(GiftCardStatus::Inactive->isTerminal())->toBeFalse()
            ->and(GiftCardStatus::Suspended->isTerminal())->toBeFalse()
            ->and(GiftCardStatus::Exhausted->isTerminal())->toBeFalse();
    });

    it('has correct status transitions', function (): void {
        expect(GiftCardStatus::Inactive->allowedTransitions())
            ->toContain(GiftCardStatus::Active)
            ->toContain(GiftCardStatus::Cancelled);

        expect(GiftCardStatus::Active->allowedTransitions())
            ->toContain(GiftCardStatus::Suspended)
            ->toContain(GiftCardStatus::Exhausted)
            ->toContain(GiftCardStatus::Expired)
            ->toContain(GiftCardStatus::Cancelled);

        expect(GiftCardStatus::Suspended->allowedTransitions())
            ->toContain(GiftCardStatus::Active)
            ->toContain(GiftCardStatus::Cancelled);

        expect(GiftCardStatus::Exhausted->allowedTransitions())
            ->toContain(GiftCardStatus::Active)
            ->toContain(GiftCardStatus::Cancelled);

        expect(GiftCardStatus::Expired->allowedTransitions())->toBeEmpty();
        expect(GiftCardStatus::Cancelled->allowedTransitions())->toBeEmpty();
    });

    it('can check transition validity', function (): void {
        expect(GiftCardStatus::Inactive->canTransitionTo(GiftCardStatus::Active))->toBeTrue()
            ->and(GiftCardStatus::Active->canTransitionTo(GiftCardStatus::Suspended))->toBeTrue()
            ->and(GiftCardStatus::Expired->canTransitionTo(GiftCardStatus::Active))->toBeFalse()
            ->and(GiftCardStatus::Cancelled->canTransitionTo(GiftCardStatus::Active))->toBeFalse();
    });

    it('has colors for UI display', function (): void {
        expect(GiftCardStatus::Inactive->color())->toBe('gray')
            ->and(GiftCardStatus::Active->color())->toBe('success')
            ->and(GiftCardStatus::Suspended->color())->toBe('warning')
            ->and(GiftCardStatus::Exhausted->color())->toBe('info')
            ->and(GiftCardStatus::Expired->color())->toBe('danger')
            ->and(GiftCardStatus::Cancelled->color())->toBe('danger');
    });

    it('provides options for form selects', function (): void {
        $options = GiftCardStatus::options();

        expect($options)->toBeArray()
            ->and($options)->toHaveCount(6)
            ->and($options['active'])->toBe('Active')
            ->and($options['expired'])->toBe('Expired');
    });
});

describe('GiftCardTransactionType Enum', function (): void {
    it('has correct labels for all types', function (): void {
        expect(GiftCardTransactionType::Issue->label())->toBe('Issued')
            ->and(GiftCardTransactionType::Activate->label())->toBe('Activated')
            ->and(GiftCardTransactionType::Redeem->label())->toBe('Redeemed')
            ->and(GiftCardTransactionType::TopUp->label())->toBe('Top Up')
            ->and(GiftCardTransactionType::Refund->label())->toBe('Refund')
            ->and(GiftCardTransactionType::Transfer->label())->toBe('Transfer')
            ->and(GiftCardTransactionType::Expire->label())->toBe('Expired')
            ->and(GiftCardTransactionType::Fee->label())->toBe('Fee')
            ->and(GiftCardTransactionType::Adjustment->label())->toBe('Adjustment')
            ->and(GiftCardTransactionType::Merge->label())->toBe('Merged');
    });

    it('has descriptions for all types', function (): void {
        expect(GiftCardTransactionType::Issue->description())->toContain('issued')
            ->and(GiftCardTransactionType::Redeem->description())->toContain('purchase')
            ->and(GiftCardTransactionType::TopUp->description())->toContain('added')
            ->and(GiftCardTransactionType::Refund->description())->toContain('refund');
    });

    it('correctly identifies credit types', function (): void {
        expect(GiftCardTransactionType::Issue->isCredit())->toBeTrue()
            ->and(GiftCardTransactionType::TopUp->isCredit())->toBeTrue()
            ->and(GiftCardTransactionType::Refund->isCredit())->toBeTrue()
            ->and(GiftCardTransactionType::Merge->isCredit())->toBeTrue()
            ->and(GiftCardTransactionType::Redeem->isCredit())->toBeFalse()
            ->and(GiftCardTransactionType::Transfer->isCredit())->toBeFalse()
            ->and(GiftCardTransactionType::Expire->isCredit())->toBeFalse()
            ->and(GiftCardTransactionType::Fee->isCredit())->toBeFalse();
    });

    it('correctly identifies debit types', function (): void {
        expect(GiftCardTransactionType::Redeem->isDebit())->toBeTrue()
            ->and(GiftCardTransactionType::Transfer->isDebit())->toBeTrue()
            ->and(GiftCardTransactionType::Expire->isDebit())->toBeTrue()
            ->and(GiftCardTransactionType::Fee->isDebit())->toBeTrue()
            ->and(GiftCardTransactionType::Issue->isDebit())->toBeFalse()
            ->and(GiftCardTransactionType::TopUp->isDebit())->toBeFalse()
            ->and(GiftCardTransactionType::Refund->isDebit())->toBeFalse()
            ->and(GiftCardTransactionType::Merge->isDebit())->toBeFalse();
    });

    it('correctly identifies types affecting balance', function (): void {
        expect(GiftCardTransactionType::Issue->affectsBalance())->toBeTrue()
            ->and(GiftCardTransactionType::Redeem->affectsBalance())->toBeTrue()
            ->and(GiftCardTransactionType::TopUp->affectsBalance())->toBeTrue()
            ->and(GiftCardTransactionType::Activate->affectsBalance())->toBeFalse();
    });

    it('has correct expected signs', function (): void {
        expect(GiftCardTransactionType::Issue->expectedSign())->toBe(1)
            ->and(GiftCardTransactionType::TopUp->expectedSign())->toBe(1)
            ->and(GiftCardTransactionType::Refund->expectedSign())->toBe(1)
            ->and(GiftCardTransactionType::Redeem->expectedSign())->toBe(-1)
            ->and(GiftCardTransactionType::Transfer->expectedSign())->toBe(-1)
            ->and(GiftCardTransactionType::Expire->expectedSign())->toBe(-1)
            ->and(GiftCardTransactionType::Fee->expectedSign())->toBe(-1)
            ->and(GiftCardTransactionType::Activate->expectedSign())->toBe(0);
    });

    it('correctly identifies types requiring reference', function (): void {
        expect(GiftCardTransactionType::Redeem->requiresReference())->toBeTrue()
            ->and(GiftCardTransactionType::Refund->requiresReference())->toBeTrue()
            ->and(GiftCardTransactionType::Issue->requiresReference())->toBeFalse()
            ->and(GiftCardTransactionType::TopUp->requiresReference())->toBeFalse()
            ->and(GiftCardTransactionType::Transfer->requiresReference())->toBeFalse();
    });

    it('has colors for UI display', function (): void {
        expect(GiftCardTransactionType::Issue->color())->toBe('primary')
            ->and(GiftCardTransactionType::Activate->color())->toBe('info')
            ->and(GiftCardTransactionType::Redeem->color())->toBe('warning')
            ->and(GiftCardTransactionType::TopUp->color())->toBe('success')
            ->and(GiftCardTransactionType::Refund->color())->toBe('success')
            ->and(GiftCardTransactionType::Expire->color())->toBe('danger')
            ->and(GiftCardTransactionType::Fee->color())->toBe('danger');
    });

    it('provides options for form selects', function (): void {
        $options = GiftCardTransactionType::options();

        expect($options)->toBeArray()
            ->and($options)->toHaveCount(10)
            ->and($options['redeem'])->toBe('Redeemed')
            ->and($options['topup'])->toBe('Top Up');
    });
});
