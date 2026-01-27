<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentGatewayResolver;

describe('PaymentGatewayResolver', function (): void {
    it('can register a processor', function (): void {
        $resolver = new PaymentGatewayResolver(null, ['test']);
        $processor = createMockProcessor('test');

        $resolver->register('test', $processor);

        expect($resolver->hasGateway('test'))->toBeTrue();
    });

    it('can resolve a registered processor', function (): void {
        $resolver = new PaymentGatewayResolver(null, ['test']);
        $processor = createMockProcessor('test');

        $resolver->register('test', $processor);

        expect($resolver->resolve('test'))->toBe($processor);
    });

    it('throws exception when resolving unregistered gateway', function (): void {
        $resolver = new PaymentGatewayResolver(null, []);

        expect(fn () => $resolver->resolve('nonexistent'))
            ->toThrow(MissingPaymentGatewayException::class);
    });

    it('uses configured default gateway', function (): void {
        $resolver = new PaymentGatewayResolver('test', ['test']);
        $processor = createMockProcessor('test');

        $resolver->register('test', $processor);

        expect($resolver->getDefaultGateway())->toBe('test');
    });

    it('falls back to priority order when no default', function (): void {
        $resolver = new PaymentGatewayResolver(null, ['first', 'second']);
        $processor1 = createMockProcessor('first');
        $processor2 = createMockProcessor('second');

        $resolver->register('second', $processor2);
        $resolver->register('first', $processor1);

        expect($resolver->getDefaultGateway())->toBe('first');
    });

    it('falls back to first available when no priority match', function (): void {
        $resolver = new PaymentGatewayResolver(null, ['unregistered']);
        $processor = createMockProcessor('available');

        $resolver->register('available', $processor);

        expect($resolver->getDefaultGateway())->toBe('available');
    });

    it('throws exception when no gateway available', function (): void {
        $resolver = new PaymentGatewayResolver(null, []);

        expect(fn () => $resolver->getDefaultGateway())
            ->toThrow(MissingPaymentGatewayException::class);
    });

    it('can check if gateway exists', function (): void {
        $resolver = new PaymentGatewayResolver(null, []);
        $processor = createMockProcessor('test');

        $resolver->register('test', $processor);

        expect($resolver->hasGateway('test'))->toBeTrue()
            ->and($resolver->hasGateway('nonexistent'))->toBeFalse();
    });

    it('can get all available gateways', function (): void {
        $resolver = new PaymentGatewayResolver(null, []);
        $processor1 = createMockProcessor('gateway1');
        $processor2 = createMockProcessor('gateway2');

        $resolver->register('gateway1', $processor1);
        $resolver->register('gateway2', $processor2);

        $available = $resolver->getAvailable();

        expect($available)->toHaveCount(2)
            ->and($available)->toHaveKey('gateway1')
            ->and($available)->toHaveKey('gateway2');
    });

    it('resolves to default when no specific gateway requested', function (): void {
        $resolver = new PaymentGatewayResolver('default', ['default']);
        $processor = createMockProcessor('default');

        $resolver->register('default', $processor);

        expect($resolver->resolve(null))->toBe($processor);
    });
});

/**
 * Helper function to create a mock payment processor.
 */
function createMockProcessor(string $identifier): PaymentProcessorInterface
{
    return new class($identifier) implements PaymentProcessorInterface
    {
        public function __construct(
            private readonly string $identifier,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return ucfirst($this->identifier);
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            return PaymentResult::success('pay_123');
        }

        public function handleCallback(array $payload): PaymentResult
        {
            return new PaymentResult(PaymentStatus::Completed, 'pay_123');
        }

        public function getRedirectUrl(CheckoutSession $session): ?string
        {
            return null;
        }

        public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
        {
            return new PaymentResult(PaymentStatus::Refunded, $paymentId, amount: $amount);
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            return new PaymentResult(PaymentStatus::Completed, $paymentId);
        }
    };
}
