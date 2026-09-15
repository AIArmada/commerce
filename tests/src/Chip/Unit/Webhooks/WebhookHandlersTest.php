<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\Handlers\PaymentFailedHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseCancelledHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\Handlers\PurchaseRefundedHandler;
use AIArmada\Chip\Webhooks\Handlers\WebhookHandler;

describe('WebhookHandler interface implementations', function (): void {
    describe('PurchasePaidHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = app(PurchasePaidHandler::class);
            expect($handler)->toBeInstanceOf(PurchasePaidHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = app(PurchasePaidHandler::class);
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });

        it('has handle method', function (): void {
            expect(method_exists(PurchasePaidHandler::class, 'handle'))->toBeTrue();
        });
    });

    describe('PurchaseCancelledHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = app(PurchaseCancelledHandler::class);
            expect($handler)->toBeInstanceOf(PurchaseCancelledHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = app(PurchaseCancelledHandler::class);
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('PaymentFailedHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = app(PaymentFailedHandler::class);
            expect($handler)->toBeInstanceOf(PaymentFailedHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = app(PaymentFailedHandler::class);
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

    describe('PurchaseRefundedHandler', function (): void {
        it('can be instantiated', function (): void {
            $handler = app(PurchaseRefundedHandler::class);
            expect($handler)->toBeInstanceOf(PurchaseRefundedHandler::class);
        });

        it('implements WebhookHandler interface', function (): void {
            $handler = app(PurchaseRefundedHandler::class);
            expect($handler)->toBeInstanceOf(WebhookHandler::class);
        });
    });

});
