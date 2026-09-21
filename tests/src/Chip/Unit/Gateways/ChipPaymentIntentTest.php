<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use Akaunting\Money\Money;

describe('ChipPaymentIntent', function (): void {
    beforeEach(function (): void {
        $this->purchaseData = [
            'id' => 'purchase-123',
            'reference' => 'ORDER-001',
            'status' => 'paid',
            'is_test' => true,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase-123',
            'success_redirect' => 'https://example.com/success',
            'failure_redirect' => 'https://example.com/failed',
            'marked_as_paid' => false,
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'currency' => 'MYR',
                'total' => 10000,
                'products' => [],
            ],
        ];
    });

    it('implements PaymentIntentInterface', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent)->toBeInstanceOf(PaymentIntentInterface::class);
    });

    it('returns payment id', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getPaymentId())->toBe('purchase-123');
    });

    it('returns reference', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getReference())->toBe('ORDER-001');
    });

    it('returns amount as Money', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        $amount = $intent->getAmount();

        expect($amount)->toBeInstanceOf(Money::class)
            ->and($amount->getAmount())->toBe(10000);
    });

    it('returns checkout url', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getCheckoutUrl())->toBe('https://gate.chip-in.asia/checkout/purchase-123');
    });

    it('returns success and failure urls', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getSuccessUrl())->toBe('https://example.com/success')
            ->and($intent->getFailureUrl())->toBe('https://example.com/failed');
    });

    it('returns test mode status', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isTest())->toBeTrue();
    });

    it('returns raw response as array', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        $rawResponse = $intent->getRawResponse();

        expect($rawResponse)->toBeArray()
            ->and($rawResponse['id'])->toBe('purchase-123');
    });

    it('provides access to underlying purchase', function (): void {
        $purchase = PurchaseData::from($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getPurchase())->toBe($purchase);
    });

    describe('timestamps', function (): void {
        it('returns created at timestamp', function (): void {
            $now = time();
            $data = array_merge($this->purchaseData, ['created_on' => $now]);
            $purchase = PurchaseData::from($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getCreatedAt())->toBeInstanceOf(DateTimeInterface::class);
        });

        it('returns updated at timestamp', function (): void {
            $now = time();
            $data = array_merge($this->purchaseData, ['updated_on' => $now]);
            $purchase = PurchaseData::from($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getUpdatedAt())->toBeInstanceOf(DateTimeInterface::class);
        });
    });

    describe('refundable amount', function (): void {
        it('returns refundable amount as Money', function (): void {
            $data = array_merge($this->purchaseData, ['refundable_amount' => 5000]);
            $purchase = PurchaseData::from($data);
            $intent = new ChipPaymentIntent($purchase);

            $refundable = $intent->getRefundableAmount();

            expect($refundable)->toBeInstanceOf(Money::class)
                ->and($refundable->getAmount())->toBe(5000);
        });
    });
});
