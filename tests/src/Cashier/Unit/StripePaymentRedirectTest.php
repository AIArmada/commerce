<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\Stripe\StripePayment;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Payment;

uses(CashierTestCase::class);

describe('StripePayment redirect handling', function (): void {
    it('throws a clear exception when redirect URL is unavailable', function (): void {
        $paymentIntent = new class
        {
            public string $id = 'pi_missing_redirect_url';

            public string $status = 'requires_action';

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'next_action' => [
                        'type' => 'use_stripe_sdk',
                    ],
                ];
            }
        };

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('requiresAction')->andReturn(true);
        $payment->shouldReceive('asStripePaymentIntent')->andReturn($paymentIntent);

        $stripePayment = new StripePayment($payment);

        expect(fn () => $stripePayment->redirect())
            ->toThrow(InvalidArgumentException::class, 'Stripe payment requires action but no redirect URL is available.');
    });

    it('redirects when a valid Stripe redirect URL is present', function (): void {
        $paymentIntent = new class
        {
            public string $id = 'pi_with_redirect_url';

            public string $status = 'requires_action';

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'next_action' => [
                        'redirect_to_url' => [
                            'url' => 'https://checkout.stripe.test/next-step',
                        ],
                    ],
                ];
            }
        };

        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('requiresAction')->andReturn(true);
        $payment->shouldReceive('asStripePaymentIntent')->andReturn($paymentIntent);

        $stripePayment = new StripePayment($payment);

        $response = $stripePayment->redirect();

        expect($response)->toBeInstanceOf(RedirectResponse::class)
            ->and($response->getTargetUrl())->toBe('https://checkout.stripe.test/next-step');
    });
});
