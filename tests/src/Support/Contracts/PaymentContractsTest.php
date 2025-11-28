<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use Akaunting\Money\Money;

describe('Payment Contracts', function (): void {
    describe('LineItemInterface', function (): void {
        it('defines required line item methods', function (): void {
            $reflection = new ReflectionClass(LineItemInterface::class);

            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('getLineItemId')
                ->and($methods)->toContain('getLineItemName')
                ->and($methods)->toContain('getLineItemPrice')
                ->and($methods)->toContain('getLineItemQuantity')
                ->and($methods)->toContain('getLineItemDiscount')
                ->and($methods)->toContain('getLineItemTaxPercent')
                ->and($methods)->toContain('getLineItemSubtotal')
                ->and($methods)->toContain('getLineItemCategory')
                ->and($methods)->toContain('getLineItemMetadata');
        });

        it('requires Money return type for price methods', function (): void {
            $reflection = new ReflectionClass(LineItemInterface::class);

            $priceMethod = $reflection->getMethod('getLineItemPrice');
            $discountMethod = $reflection->getMethod('getLineItemDiscount');
            $subtotalMethod = $reflection->getMethod('getLineItemSubtotal');

            expect((string) $priceMethod->getReturnType())->toBe(Money::class)
                ->and((string) $discountMethod->getReturnType())->toBe(Money::class)
                ->and((string) $subtotalMethod->getReturnType())->toBe(Money::class);
        });
    });

    describe('CheckoutableInterface', function (): void {
        it('defines required checkout methods', function (): void {
            $reflection = new ReflectionClass(CheckoutableInterface::class);

            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('getCheckoutLineItems')
                ->and($methods)->toContain('getCheckoutSubtotal')
                ->and($methods)->toContain('getCheckoutDiscount')
                ->and($methods)->toContain('getCheckoutTax')
                ->and($methods)->toContain('getCheckoutTotal')
                ->and($methods)->toContain('getCheckoutCurrency')
                ->and($methods)->toContain('getCheckoutReference')
                ->and($methods)->toContain('getCheckoutNotes')
                ->and($methods)->toContain('getCheckoutMetadata');
        });

        it('requires Money return type for amount methods', function (): void {
            $reflection = new ReflectionClass(CheckoutableInterface::class);

            $subtotalMethod = $reflection->getMethod('getCheckoutSubtotal');
            $discountMethod = $reflection->getMethod('getCheckoutDiscount');
            $taxMethod = $reflection->getMethod('getCheckoutTax');
            $totalMethod = $reflection->getMethod('getCheckoutTotal');

            expect((string) $subtotalMethod->getReturnType())->toBe(Money::class)
                ->and((string) $discountMethod->getReturnType())->toBe(Money::class)
                ->and((string) $taxMethod->getReturnType())->toBe(Money::class)
                ->and((string) $totalMethod->getReturnType())->toBe(Money::class);
        });
    });

    describe('CustomerInterface', function (): void {
        it('defines required customer methods', function (): void {
            $reflection = new ReflectionClass(CustomerInterface::class);

            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            // Core identity methods
            expect($methods)->toContain('getCustomerEmail')
                ->and($methods)->toContain('getCustomerName')
                ->and($methods)->toContain('getCustomerPhone')
                ->and($methods)->toContain('getCustomerCountry')
                // Billing address methods
                ->and($methods)->toContain('getBillingStreetAddress')
                ->and($methods)->toContain('getBillingCity')
                ->and($methods)->toContain('getBillingState')
                ->and($methods)->toContain('getBillingPostalCode')
                ->and($methods)->toContain('getBillingCountry')
                // Shipping methods
                ->and($methods)->toContain('hasShippingAddress')
                ->and($methods)->toContain('getShippingStreetAddress')
                ->and($methods)->toContain('getShippingCity')
                ->and($methods)->toContain('getShippingState')
                ->and($methods)->toContain('getShippingPostalCode')
                ->and($methods)->toContain('getShippingCountry')
                // Additional
                ->and($methods)->toContain('getGatewayCustomerId')
                ->and($methods)->toContain('getCustomerMetadata');
        });
    });

    describe('PaymentIntentInterface', function (): void {
        it('defines required payment intent methods', function (): void {
            $reflection = new ReflectionClass(PaymentIntentInterface::class);

            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('getPaymentId')
                ->and($methods)->toContain('getReference')
                ->and($methods)->toContain('getAmount')
                ->and($methods)->toContain('getStatus')
                ->and($methods)->toContain('getCheckoutUrl')
                ->and($methods)->toContain('getSuccessUrl')
                ->and($methods)->toContain('getFailureUrl')
                ->and($methods)->toContain('isPaid')
                ->and($methods)->toContain('isPending')
                ->and($methods)->toContain('isFailed')
                ->and($methods)->toContain('isCancelled')
                ->and($methods)->toContain('isRefunded')
                ->and($methods)->toContain('getRefundableAmount')
                ->and($methods)->toContain('isTest')
                ->and($methods)->toContain('getGatewayName')
                ->and($methods)->toContain('getCreatedAt')
                ->and($methods)->toContain('getUpdatedAt')
                ->and($methods)->toContain('getPaidAt')
                ->and($methods)->toContain('getMetadata')
                ->and($methods)->toContain('getRawResponse');
        });

        it('requires PaymentStatus return type for status', function (): void {
            $reflection = new ReflectionClass(PaymentIntentInterface::class);
            $statusMethod = $reflection->getMethod('getStatus');

            expect((string) $statusMethod->getReturnType())->toBe(PaymentStatus::class);
        });

        it('requires Money return type for amount', function (): void {
            $reflection = new ReflectionClass(PaymentIntentInterface::class);
            $amountMethod = $reflection->getMethod('getAmount');

            expect((string) $amountMethod->getReturnType())->toBe(Money::class);
        });
    });

    describe('PaymentGatewayInterface', function (): void {
        it('defines required gateway methods', function (): void {
            $reflection = new ReflectionClass(PaymentGatewayInterface::class);

            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('createPayment')
                ->and($methods)->toContain('getPayment')
                ->and($methods)->toContain('cancelPayment')
                ->and($methods)->toContain('refundPayment')
                ->and($methods)->toContain('capturePayment')
                ->and($methods)->toContain('supports')
                ->and($methods)->toContain('getName');
        });

        it('requires PaymentIntentInterface return type for createPayment', function (): void {
            $reflection = new ReflectionClass(PaymentGatewayInterface::class);
            $createMethod = $reflection->getMethod('createPayment');

            expect((string) $createMethod->getReturnType())->toBe(PaymentIntentInterface::class);
        });

        it('accepts CheckoutableInterface and optional CustomerInterface parameters', function (): void {
            $reflection = new ReflectionClass(PaymentGatewayInterface::class);
            $createMethod = $reflection->getMethod('createPayment');
            $params = $createMethod->getParameters();

            expect((string) $params[0]->getType())->toBe(CheckoutableInterface::class);
            // Second parameter is nullable CustomerInterface
            expect($params[1]->allowsNull())->toBeTrue();
        });
    });

    describe('WebhookHandlerInterface', function (): void {
        it('defines required webhook methods', function (): void {
            $reflection = new ReflectionClass(WebhookHandlerInterface::class);

            $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

            expect($methods)->toContain('verifyWebhook')
                ->and($methods)->toContain('parseWebhook')
                ->and($methods)->toContain('getEventType')
                ->and($methods)->toContain('isPaymentEvent')
                ->and($methods)->toContain('getPaymentFromWebhook');
        });

        it('requires WebhookPayload return type for parseWebhook', function (): void {
            $reflection = new ReflectionClass(WebhookHandlerInterface::class);
            $parseMethod = $reflection->getMethod('parseWebhook');

            expect((string) $parseMethod->getReturnType())->toBe(WebhookPayload::class);
        });
    });

    describe('PaymentStatus enum', function (): void {
        it('has all required status values', function (): void {
            expect(PaymentStatus::cases())->toContain(PaymentStatus::CREATED)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::PENDING)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::PAID)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::FAILED)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::CANCELLED)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::EXPIRED)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::REFUNDED)
                ->and(PaymentStatus::cases())->toContain(PaymentStatus::PARTIALLY_REFUNDED);
        });

        it('provides human-readable labels', function (): void {
            expect(PaymentStatus::PAID->label())->toBe('Paid')
                ->and(PaymentStatus::PARTIALLY_REFUNDED->label())->toBe('Partially Refunded')
                ->and(PaymentStatus::PENDING->label())->toBe('Pending');
        });

        it('indicates terminal states correctly', function (): void {
            expect(PaymentStatus::PAID->isTerminal())->toBeTrue()
                ->and(PaymentStatus::FAILED->isTerminal())->toBeTrue()
                ->and(PaymentStatus::REFUNDED->isTerminal())->toBeTrue()
                ->and(PaymentStatus::PENDING->isTerminal())->toBeFalse()
                ->and(PaymentStatus::CREATED->isTerminal())->toBeFalse();
        });

        it('indicates successful states correctly', function (): void {
            expect(PaymentStatus::PAID->isSuccessful())->toBeTrue()
                ->and(PaymentStatus::PARTIALLY_REFUNDED->isSuccessful())->toBeTrue()
                ->and(PaymentStatus::FAILED->isSuccessful())->toBeFalse()
                ->and(PaymentStatus::CANCELLED->isSuccessful())->toBeFalse();
        });
    });

    describe('WebhookPayload', function (): void {
        it('creates webhook payload with all data', function (): void {
            $occurredAt = new \DateTimeImmutable('2024-01-15 10:30:00');
            $payload = new WebhookPayload(
                eventType: 'payment.paid',
                paymentId: 'payment-123',
                status: PaymentStatus::PAID,
                reference: 'ORDER-001',
                gatewayName: 'chip',
                occurredAt: $occurredAt,
                rawData: ['full' => 'data'],
            );

            expect($payload->eventType)->toBe('payment.paid')
                ->and($payload->paymentId)->toBe('payment-123')
                ->and($payload->status)->toBe(PaymentStatus::PAID)
                ->and($payload->reference)->toBe('ORDER-001')
                ->and($payload->gatewayName)->toBe('chip')
                ->and($payload->occurredAt)->toBe($occurredAt)
                ->and($payload->rawData)->toBe(['full' => 'data']);
        });

        it('checks for successful payment', function (): void {
            $payload = new WebhookPayload(
                eventType: 'payment.paid',
                paymentId: 'payment-123',
                status: PaymentStatus::PAID,
                reference: 'ORDER-001',
                gatewayName: 'chip',
                occurredAt: new \DateTimeImmutable(),
            );

            expect($payload->isPaymentSuccess())->toBeTrue()
                ->and($payload->isPaymentFailed())->toBeFalse();
        });

        it('checks for failed payment', function (): void {
            $payload = new WebhookPayload(
                eventType: 'payment.failed',
                paymentId: 'payment-123',
                status: PaymentStatus::FAILED,
                reference: 'ORDER-001',
                gatewayName: 'chip',
                occurredAt: new \DateTimeImmutable(),
            );

            expect($payload->isPaymentFailed())->toBeTrue()
                ->and($payload->isPaymentSuccess())->toBeFalse();
        });

        it('checks for refund status', function (): void {
            $payload = new WebhookPayload(
                eventType: 'payment.refunded',
                paymentId: 'payment-123',
                status: PaymentStatus::REFUNDED,
                reference: 'ORDER-001',
                gatewayName: 'chip',
                occurredAt: new \DateTimeImmutable(),
            );

            expect($payload->isRefund())->toBeTrue();
        });
    });
});
