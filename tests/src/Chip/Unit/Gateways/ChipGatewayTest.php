<?php

declare(strict_types=1);

use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\Chip\Gateways\ChipPaymentIntent;
use AIArmada\Chip\Gateways\ChipWebhookHandler;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;

describe('ChipGateway', function (): void {
    beforeEach(function (): void {
        $this->collectService = Mockery::mock(ChipCollectService::class);
        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->gateway = new ChipGateway($this->collectService, $this->webhookService);
    });

    it('implements PaymentGatewayInterface', function (): void {
        expect($this->gateway)->toBeInstanceOf(PaymentGatewayInterface::class);
    });

    it('returns gateway name', function (): void {
        expect($this->gateway->getName())->toBe('chip');
    });

    it('returns display name', function (): void {
        expect($this->gateway->getDisplayName())->toBe('CHIP');
    });

    it('returns test mode status', function (): void {
        config(['chip.environment' => 'sandbox']);
        expect($this->gateway->isTestMode())->toBeTrue();
    });

    describe('supports features', function (): void {
        it('supports refunds', function (): void {
            expect($this->gateway->supports('refunds'))->toBeTrue();
        });

        it('supports partial refunds', function (): void {
            expect($this->gateway->supports('partial_refunds'))->toBeTrue();
        });

        it('supports pre-authorization', function (): void {
            expect($this->gateway->supports('pre_authorization'))->toBeTrue();
        });

        it('supports recurring payments', function (): void {
            expect($this->gateway->supports('recurring'))->toBeTrue();
        });

        it('supports webhooks', function (): void {
            expect($this->gateway->supports('webhooks'))->toBeTrue();
        });

        it('supports hosted checkout', function (): void {
            expect($this->gateway->supports('hosted_checkout'))->toBeTrue();
        });

        it('does not support embedded checkout', function (): void {
            expect($this->gateway->supports('embedded_checkout'))->toBeFalse();
        });

        it('supports direct charge', function (): void {
            expect($this->gateway->supports('direct_charge'))->toBeTrue();
        });

        it('returns false for unknown features', function (): void {
            expect($this->gateway->supports('unknown_feature'))->toBeFalse();
        });
    });

    describe('webhook handler', function (): void {
        it('returns webhook handler instance', function (): void {
            $handler = $this->gateway->getWebhookHandler();

            expect($handler)->toBeInstanceOf(WebhookHandlerInterface::class)
                ->and($handler)->toBeInstanceOf(ChipWebhookHandler::class);
        });
    });
});
