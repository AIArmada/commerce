<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\CancellationReason;

describe('CancellationReason Enum', function (): void {
    it('has correct string values', function (): void {
        expect(CancellationReason::CUSTOMER_REQUEST->value)->toBe('customer_request');
        expect(CancellationReason::OUT_OF_STOCK->value)->toBe('out_of_stock');
        expect(CancellationReason::INCORRECT_ADDRESS->value)->toBe('incorrect_address');
        expect(CancellationReason::PAYMENT_FAILED->value)->toBe('payment_failed');
        expect(CancellationReason::SYSTEM_ERROR->value)->toBe('system_error');
        expect(CancellationReason::OTHER->value)->toBe('other');
    });

    it('returns correct descriptions', function (): void {
        expect(CancellationReason::CUSTOMER_REQUEST->getDescription())->toBe('Customer requested cancellation');
        expect(CancellationReason::OUT_OF_STOCK->getDescription())->toBe('Product is out of stock');
        expect(CancellationReason::INCORRECT_ADDRESS->getDescription())->toBe('Incorrect or incomplete delivery address');
        expect(CancellationReason::PAYMENT_FAILED->getDescription())->toBe('Payment processing failed');
    });

    it('correctly identifies reasons requiring customer contact', function (): void {
        // Should contact customer
        expect(CancellationReason::OUT_OF_STOCK->requiresCustomerContact())->toBeTrue();
        expect(CancellationReason::INCORRECT_PRICING->requiresCustomerContact())->toBeTrue();
        expect(CancellationReason::UNABLE_TO_FULFILL->requiresCustomerContact())->toBeTrue();
        expect(CancellationReason::PAYMENT_FAILED->requiresCustomerContact())->toBeTrue();
        expect(CancellationReason::ADDRESS_NOT_SERVICEABLE->requiresCustomerContact())->toBeTrue();

        // Should not contact customer
        expect(CancellationReason::CUSTOMER_REQUEST->requiresCustomerContact())->toBeFalse();
        expect(CancellationReason::CUSTOMER_CHANGED_MIND->requiresCustomerContact())->toBeFalse();
    });

    it('correctly identifies merchant responsibility', function (): void {
        // Merchant responsibility
        expect(CancellationReason::OUT_OF_STOCK->isMerchantResponsibility())->toBeTrue();
        expect(CancellationReason::INCORRECT_PRICING->isMerchantResponsibility())->toBeTrue();
        expect(CancellationReason::UNABLE_TO_FULFILL->isMerchantResponsibility())->toBeTrue();
        expect(CancellationReason::DUPLICATE_ORDER->isMerchantResponsibility())->toBeTrue();
        expect(CancellationReason::SYSTEM_ERROR->isMerchantResponsibility())->toBeTrue();

        // Not merchant responsibility
        expect(CancellationReason::CUSTOMER_REQUEST->isMerchantResponsibility())->toBeFalse();
        expect(CancellationReason::INCORRECT_ADDRESS->isMerchantResponsibility())->toBeFalse();
    });

    it('correctly identifies customer responsibility', function (): void {
        // Customer responsibility
        expect(CancellationReason::CUSTOMER_REQUEST->isCustomerResponsibility())->toBeTrue();
        expect(CancellationReason::CUSTOMER_CHANGED_MIND->isCustomerResponsibility())->toBeTrue();
        expect(CancellationReason::CUSTOMER_ORDERED_BY_MISTAKE->isCustomerResponsibility())->toBeTrue();
        expect(CancellationReason::INCORRECT_ADDRESS->isCustomerResponsibility())->toBeTrue();

        // Not customer responsibility
        expect(CancellationReason::OUT_OF_STOCK->isCustomerResponsibility())->toBeFalse();
        expect(CancellationReason::PAYMENT_FAILED->isCustomerResponsibility())->toBeFalse();
    });

    it('correctly identifies delivery issues', function (): void {
        // Delivery issues
        expect(CancellationReason::INCORRECT_ADDRESS->isDeliveryIssue())->toBeTrue();
        expect(CancellationReason::ADDRESS_NOT_SERVICEABLE->isDeliveryIssue())->toBeTrue();
        expect(CancellationReason::DELIVERY_NOT_AVAILABLE->isDeliveryIssue())->toBeTrue();

        // Not delivery issues
        expect(CancellationReason::CUSTOMER_REQUEST->isDeliveryIssue())->toBeFalse();
        expect(CancellationReason::OUT_OF_STOCK->isDeliveryIssue())->toBeFalse();
    });

    it('correctly identifies payment issues', function (): void {
        // Payment issues
        expect(CancellationReason::PAYMENT_FAILED->isPaymentIssue())->toBeTrue();
        expect(CancellationReason::PAYMENT_PENDING_TOO_LONG->isPaymentIssue())->toBeTrue();

        // Not payment issues
        expect(CancellationReason::CUSTOMER_REQUEST->isPaymentIssue())->toBeFalse();
        expect(CancellationReason::OUT_OF_STOCK->isPaymentIssue())->toBeFalse();
    });

    it('returns correct categories', function (): void {
        // Customer-Initiated
        expect(CancellationReason::CUSTOMER_REQUEST->getCategory())->toBe('Customer-Initiated');
        expect(CancellationReason::CUSTOMER_CHANGED_MIND->getCategory())->toBe('Customer-Initiated');

        // Merchant-Initiated
        expect(CancellationReason::OUT_OF_STOCK->getCategory())->toBe('Merchant-Initiated');
        expect(CancellationReason::DUPLICATE_ORDER->getCategory())->toBe('Merchant-Initiated');

        // Delivery Issue
        expect(CancellationReason::INCORRECT_ADDRESS->getCategory())->toBe('Delivery Issue');
        expect(CancellationReason::ADDRESS_NOT_SERVICEABLE->getCategory())->toBe('Delivery Issue');

        // Payment Issue
        expect(CancellationReason::PAYMENT_FAILED->getCategory())->toBe('Payment Issue');

        // System Error
        expect(CancellationReason::SYSTEM_ERROR->getCategory())->toBe('System Error');

        // Other
        expect(CancellationReason::OTHER->getCategory())->toBe('Other');
    });

    it('returns all customer-initiated reasons', function (): void {
        $reasons = CancellationReason::customerInitiated();

        expect($reasons)->toHaveCount(4);
        expect($reasons)->toContain(CancellationReason::CUSTOMER_REQUEST);
        expect($reasons)->toContain(CancellationReason::CUSTOMER_CHANGED_MIND);
        expect($reasons)->toContain(CancellationReason::CUSTOMER_ORDERED_BY_MISTAKE);
        expect($reasons)->toContain(CancellationReason::CUSTOMER_FOUND_BETTER_PRICE);
    });

    it('returns all merchant-initiated reasons', function (): void {
        $reasons = CancellationReason::merchantInitiated();

        expect($reasons)->toHaveCount(4);
        expect($reasons)->toContain(CancellationReason::OUT_OF_STOCK);
        expect($reasons)->toContain(CancellationReason::INCORRECT_PRICING);
        expect($reasons)->toContain(CancellationReason::UNABLE_TO_FULFILL);
        expect($reasons)->toContain(CancellationReason::DUPLICATE_ORDER);
    });

    it('returns all delivery issue reasons', function (): void {
        $reasons = CancellationReason::deliveryIssues();

        expect($reasons)->toHaveCount(3);
        expect($reasons)->toContain(CancellationReason::INCORRECT_ADDRESS);
        expect($reasons)->toContain(CancellationReason::ADDRESS_NOT_SERVICEABLE);
        expect($reasons)->toContain(CancellationReason::DELIVERY_NOT_AVAILABLE);
    });

    it('returns all payment issue reasons', function (): void {
        $reasons = CancellationReason::paymentIssues();

        expect($reasons)->toHaveCount(2);
        expect($reasons)->toContain(CancellationReason::PAYMENT_FAILED);
        expect($reasons)->toContain(CancellationReason::PAYMENT_PENDING_TOO_LONG);
    });

    it('creates from string (case-insensitive)', function (): void {
        expect(CancellationReason::fromString('customer_request'))->toBe(CancellationReason::CUSTOMER_REQUEST);
        expect(CancellationReason::fromString('CUSTOMER_REQUEST'))->toBe(CancellationReason::CUSTOMER_REQUEST);
        expect(CancellationReason::fromString('out_of_stock'))->toBe(CancellationReason::OUT_OF_STOCK);

        // Unknown reason returns null
        expect(CancellationReason::fromString('unknown_reason'))->toBeNull();
    });
});

describe('CancellationReason - Real-World Scenarios', function (): void {
    it('handles out of stock scenario', function (): void {
        $reason = CancellationReason::OUT_OF_STOCK;

        expect($reason->getCategory())->toBe('Merchant-Initiated');
        expect($reason->isMerchantResponsibility())->toBeTrue();
        expect($reason->requiresCustomerContact())->toBeTrue();
        expect($reason->getDescription())->toBe('Product is out of stock');
    });

    it('handles customer changed mind scenario', function (): void {
        $reason = CancellationReason::CUSTOMER_CHANGED_MIND;

        expect($reason->getCategory())->toBe('Customer-Initiated');
        expect($reason->isCustomerResponsibility())->toBeTrue();
        expect($reason->requiresCustomerContact())->toBeFalse();
    });

    it('handles incorrect address scenario', function (): void {
        $reason = CancellationReason::INCORRECT_ADDRESS;

        expect($reason->getCategory())->toBe('Delivery Issue');
        expect($reason->isDeliveryIssue())->toBeTrue();
        expect($reason->isCustomerResponsibility())->toBeTrue();
    });

    it('handles payment failed scenario', function (): void {
        $reason = CancellationReason::PAYMENT_FAILED;

        expect($reason->getCategory())->toBe('Payment Issue');
        expect($reason->isPaymentIssue())->toBeTrue();
        expect($reason->requiresCustomerContact())->toBeTrue();
    });

    it('handles system error scenario', function (): void {
        $reason = CancellationReason::SYSTEM_ERROR;

        expect($reason->getCategory())->toBe('System Error');
        expect($reason->isMerchantResponsibility())->toBeTrue();
        expect($reason->requiresCustomerContact())->toBeTrue();
    });
});
