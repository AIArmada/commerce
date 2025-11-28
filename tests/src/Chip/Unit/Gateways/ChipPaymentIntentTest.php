<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
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
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent)->toBeInstanceOf(PaymentIntentInterface::class);
    });

    it('returns payment id', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getPaymentId())->toBe('purchase-123');
    });

    it('returns reference', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getReference())->toBe('ORDER-001');
    });

    it('returns amount as Money', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        $amount = $intent->getAmount();

        expect($amount)->toBeInstanceOf(Money::class)
            ->and($amount->getAmount())->toBe(10000);
    });

    it('returns checkout url', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getCheckoutUrl())->toBe('https://gate.chip-in.asia/checkout/purchase-123');
    });

    it('returns success and failure urls', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getSuccessUrl())->toBe('https://example.com/success')
            ->and($intent->getFailureUrl())->toBe('https://example.com/failed');
    });

    it('returns test mode status', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->isTest())->toBeTrue();
    });

    it('returns gateway name', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getGatewayName())->toBe('chip');
    });

    it('returns raw response as array', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        $rawResponse = $intent->getRawResponse();

        expect($rawResponse)->toBeArray()
            ->and($rawResponse['id'])->toBe('purchase-123');
    });

    it('provides access to underlying purchase', function (): void {
        $purchase = Purchase::fromArray($this->purchaseData);
        $intent = new ChipPaymentIntent($purchase);

        expect($intent->getPurchase())->toBe($purchase);
    });

    describe('status mapping', function (): void {
        it('maps created status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'created']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::CREATED)
                ->and($intent->isPending())->toBeTrue()
                ->and($intent->isPaid())->toBeFalse();
        });

        it('maps pending_execute status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'pending_execute']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::PENDING);
        });

        it('maps pending_charge status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'pending_charge']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::PENDING);
        });

        it('maps paid status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'paid']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::PAID)
                ->and($intent->isPaid())->toBeTrue();
        });

        it('maps refunded status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'refunded']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::REFUNDED)
                ->and($intent->isRefunded())->toBeTrue();
        });

        it('maps partially_refunded status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'partially_refunded']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::PARTIALLY_REFUNDED);
        });

        it('maps cancelled status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'cancelled']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::CANCELLED)
                ->and($intent->isCancelled())->toBeTrue();
        });

        it('maps expired status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'expired']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::EXPIRED);
        });

        it('maps error status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'error']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::FAILED)
                ->and($intent->isFailed())->toBeTrue();
        });

        it('maps blocked status', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'blocked']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::FAILED);
        });

        it('maps hold/preauthorized status to authorized', function (): void {
            $data = array_merge($this->purchaseData, ['status' => 'hold']);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getStatus())->toBe(PaymentStatus::AUTHORIZED);
        });
    });

    describe('timestamps', function (): void {
        it('returns created at timestamp', function (): void {
            $now = time();
            $data = array_merge($this->purchaseData, ['created_on' => $now]);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getCreatedAt())->toBeInstanceOf(\DateTimeInterface::class);
        });

        it('returns updated at timestamp', function (): void {
            $now = time();
            $data = array_merge($this->purchaseData, ['updated_on' => $now]);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            expect($intent->getUpdatedAt())->toBeInstanceOf(\DateTimeInterface::class);
        });
    });

    describe('refundable amount', function (): void {
        it('returns refundable amount as Money', function (): void {
            $data = array_merge($this->purchaseData, ['refundable_amount' => 5000]);
            $purchase = Purchase::fromArray($data);
            $intent = new ChipPaymentIntent($purchase);

            $refundable = $intent->getRefundableAmount();

            expect($refundable)->toBeInstanceOf(Money::class)
                ->and($refundable->getAmount())->toBe(5000);
        });
    });
});
