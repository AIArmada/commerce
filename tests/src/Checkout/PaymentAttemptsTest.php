<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;

use function Pest\Laravel\mock;

it('increments payment attempts atomically and enforces the retry limit', function (): void {
    config()->set('checkout.payment.retry_limit', 1);

    $processor = mock(PaymentProcessorInterface::class);
    $processor->shouldReceive('getIdentifier')->andReturn('chip');
    $processor->shouldReceive('isAvailable')->twice()->andReturnTrue();
    $processor->shouldReceive('createPayment')
        ->once()
        ->andReturn(PaymentResult::pending('pay_atomic', 'https://gateway.example.test/pay'));

    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('resolve')->with('chip')->andReturn($processor);

    $session = CheckoutSession::create([
        'cart_id' => 'payment-attempt-race',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 1000,
        'currency' => 'MYR',
    ]);
    $staleSession = $session->fresh();

    $step = new ProcessPaymentStep($resolver);
    $firstResult = $step->handle($session);

    expect($firstResult->isSuccessful())->toBeTrue()
        ->and($session->fresh()->payment_attempts)->toBe(1);

    expect(fn (): mixed => $step->handle($staleSession))
        ->toThrow(PaymentException::class, 'Payment retry limit exceeded');

    expect($session->fresh()->payment_attempts)->toBe(1);
});
