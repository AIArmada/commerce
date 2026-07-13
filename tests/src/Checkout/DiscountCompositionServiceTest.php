<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\DiscountProvider;
use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\DiscountCompositionService;

function mockSession(int $subtotal = 10000): CheckoutSession
{
    $session = Mockery::mock(CheckoutSession::class)->shouldIgnoreMissing();
    $session->shouldReceive('getAttribute')->with('subtotal')->andReturn($subtotal);
    $session->shouldReceive('getAttribute')->with('discount_data')->andReturn([]);
    $session->shouldReceive('getAttribute')->with('cart_snapshot')->andReturn([]);

    return $session;
}

it('allocates proposals within cap and caps total at eligible subtotal', function (): void {
    $session = mockSession(10000);

    $provider = new class implements DiscountProvider {
        public int $callCount = 0;
        public function providerKey(): string { return 'test'; }
        public function evaluate(CheckoutSession $s, array $d): array {
            $this->callCount++;
            return [
                new DiscountProposal('test', 'a', 6000, priority: 10),
                new DiscountProposal('test', 'b', 6000, priority: 5),
            ];
        }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $service = new DiscountCompositionService([$provider]);
    $result = $service->evaluate($session, []);

    expect($result['totalDiscount'])->toBe(10000);
    expect($result['allocations'])->toHaveCount(2);
    expect($result['allocations'][0]->requestedAmount)->toBe(6000);
    expect($result['allocations'][1]->requestedAmount)->toBe(4000);
});

it('deterministic allocation by priority, provider key, candidate key', function (): void {
    $session = mockSession(5000);

    $providerA = new class implements DiscountProvider {
        public function providerKey(): string { return 'aaa'; }
        public function evaluate(CheckoutSession $s, array $d): array {
            return [new DiscountProposal('aaa', 'x', 3000, priority: 0)];
        }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $providerB = new class implements DiscountProvider {
        public function providerKey(): string { return 'bbb'; }
        public function evaluate(CheckoutSession $s, array $d): array {
            return [new DiscountProposal('bbb', 'y', 3000, priority: 10)];
        }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $service = new DiscountCompositionService([$providerA, $providerB]);
    $result = $service->evaluate($session, []);

    expect($result['allocations'])->toHaveCount(2);
    expect($result['allocations'][0]->providerKey)->toBe('bbb');
    expect($result['allocations'][1]->providerKey)->toBe('aaa');
});

it('zero subtotal produces no allocations', function (): void {
    $session = mockSession(0);

    $provider = new class implements DiscountProvider {
        public function providerKey(): string { return 'test'; }
        public function evaluate(CheckoutSession $s, array $d): array {
            return [new DiscountProposal('test', 'a', 5000)];
        }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $service = new DiscountCompositionService([$provider]);
    $result = $service->evaluate($session, []);

    expect($result['totalDiscount'])->toBe(0);
    expect($result['allocations'])->toHaveCount(0);
});

it('no providers returns empty result', function (): void {
    $session = mockSession();

    $service = new DiscountCompositionService([]);
    $result = $service->evaluate($session, []);

    expect($result['totalDiscount'])->toBe(0);
    expect($result['allocations'])->toHaveCount(0);
});

it('commit delegates to providers by accepted proposals', function (): void {
    $session = mockSession();

    $provider = new class implements DiscountProvider {
        public function providerKey(): string { return 'test'; }
        public function evaluate(CheckoutSession $s, array $d): array { return []; }
        public function commit(CheckoutSession $s, array $a): array {
            $commitments = [];
            foreach ($a as $p) {
                $commitments[$p->candidateKey] = new DiscountCommitment(
                    'test', $p->candidateKey, $p->requestedAmount, 'tok_' . $p->candidateKey,
                );
            }
            return $commitments;
        }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $service = new DiscountCompositionService([$provider]);
    $accepted = [new DiscountProposal('test', 'abc', 500)];
    $commitments = $service->commit($session, $accepted);

    expect($commitments)->toHaveCount(1);
    expect($commitments['abc']->appliedAmount)->toBe(500);
});

it('release delegates commitments to providers', function (): void {
    $session = mockSession();

    $released = [];
    $provider = new class($released) implements DiscountProvider {
        public function __construct(private array &$released) {}
        public function providerKey(): string { return 'test'; }
        public function evaluate(CheckoutSession $s, array $d): array { return []; }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {
            foreach ($c as $commitment) {
                $this->released[] = $commitment->candidateKey;
            }
        }
    };

    $commitments = [
        'test:abc' => new DiscountCommitment('test', 'abc', 500, 'tok_1'),
        'test:def' => new DiscountCommitment('test', 'def', 300, 'tok_2'),
    ];

    $service = new DiscountCompositionService([$provider]);
    $service->release($session, $commitments);

    expect($released)->toBe(['abc', 'def']);
});

it('allocates promotions first then vouchers when both exceed cap', function (): void {
    $session = mockSession(10000);

    $promotionProvider = new class implements DiscountProvider {
        public function providerKey(): string { return 'promotions'; }
        public function evaluate(CheckoutSession $s, array $d): array {
            return [new DiscountProposal('promotions', 'promo-1', 8000, priority: 60)];
        }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $voucherProvider = new class implements DiscountProvider {
        public function providerKey(): string { return 'vouchers'; }
        public function evaluate(CheckoutSession $s, array $d): array {
            return [new DiscountProposal('vouchers', 'vc-1', 5000, priority: 50)];
        }
        public function commit(CheckoutSession $s, array $a): array { return []; }
        public function release(CheckoutSession $s, array $c): void {}
    };

    $service = new DiscountCompositionService([$promotionProvider, $voucherProvider]);
    $result = $service->evaluate($session, []);

    expect($result['totalDiscount'])->toBe(10000);
    expect($result['allocations'])->toHaveCount(2);
    expect($result['allocations'][0]->providerKey)->toBe('promotions');
    expect($result['allocations'][0]->requestedAmount)->toBe(8000);
    expect($result['allocations'][1]->providerKey)->toBe('vouchers');
    expect($result['allocations'][1]->requestedAmount)->toBe(2000);
});
