<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\Processing;

describe('State transitions', function (): void {
    it('allows processing to payment failed transition', function (): void {
        $session = new CheckoutSession;
        $session->status = new Processing($session);

        expect($session->status->canTransitionTo(PaymentFailed::class))->toBeTrue();
    });

    it('allows processing to completed transition', function (): void {
        $session = new CheckoutSession;
        $session->status = new Processing($session);

        expect($session->status->canTransitionTo(Completed::class))->toBeTrue();
    });
});
