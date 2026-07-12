<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\StepExecutor;
use Illuminate\Contracts\Events\Dispatcher;

function makeStep(string $id, ?string $dependency = null): CheckoutStepInterface
{
    return new class($id, $dependency) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $id,
            private readonly ?string $dependency,
        ) {}

        public function getIdentifier(): string { return $this->id; }

        public function getName(): string { return $this->id; }

        public function validate(CheckoutSession $session): array { return []; }

        public function handle(CheckoutSession $session): StepResult { return StepResult::success($this->id); }

        public function canSkip(CheckoutSession $session): bool { return false; }

        public function rollback(CheckoutSession $session): void {}

        public function getDependencies(): array { return $this->dependency !== null ? [$this->dependency] : []; }
    };
}

describe('StepExecutor', function (): void {
    function createExecutor(CheckoutStepRegistry $registry): StepExecutor
    {
        return new StepExecutor($registry, app(Dispatcher::class));
    }

    it('runs all steps in order', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('a', makeStep('a'));
        $registry->register('b', makeStep('b'));

        $session = CheckoutSession::create(['cart_id' => 'test-executor']);
        $result = createExecutor($registry)->run($session);

        expect($result->success)->toBeTrue()
            ->and($session->getStepState('a'))->toBe(StepStatus::Completed)
            ->and($session->getStepState('b'))->toBe(StepStatus::Completed);
    });

    it('starts from a given step', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('a', makeStep('a'));
        $registry->register('b', makeStep('b'));

        $session = CheckoutSession::create(['cart_id' => 'test-executor-from']);
        $session->setStepState('a', StepStatus::Completed);
        $result = createExecutor($registry)->run($session, fromStep: 'a');

        expect($result->success)->toBeTrue()
            ->and($session->getStepState('a'))->toBe(StepStatus::Completed)
            ->and($session->getStepState('b'))->toBe(StepStatus::Completed);
    });

    it('skips already completed steps', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('a', makeStep('a'));

        $session = CheckoutSession::create(['cart_id' => 'test-executor-skip']);
        $session->setStepState('a', StepStatus::Completed);
        $result = createExecutor($registry)->run($session);

        expect($result->success)->toBeTrue();
    });

    it('fails when a step returns failure', function (): void {
        $registry = new CheckoutStepRegistry;
        $failStep = new class implements CheckoutStepInterface {
            public function getIdentifier(): string { return 'fail'; }
            public function getName(): string { return 'fail'; }
            public function validate(CheckoutSession $session): array { return []; }
            public function handle(CheckoutSession $session): StepResult { return StepResult::failed('fail', 'oops'); }
            public function canSkip(CheckoutSession $session): bool { return false; }
            public function rollback(CheckoutSession $session): void {}
            public function getDependencies(): array { return []; }
        };
        $registry->register('fail', $failStep);

        $session = CheckoutSession::create(['cart_id' => 'test-executor-fail']);
        $result = createExecutor($registry)->run($session);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('oops');
    });

    it('throws when a dependency is not met', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('b', makeStep('b', dependency: 'a'));

        $session = CheckoutSession::create(['cart_id' => 'test-executor-dep']);
        $executor = createExecutor($registry);

        expect(fn () => $executor->run($session))
            ->toThrow(CheckoutStepException::class);
    });

    it('handles a single step via processStep', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('a', makeStep('a'));

        $session = CheckoutSession::create(['cart_id' => 'test-executor-single']);
        $step = $registry->get('a');

        $result = createExecutor($registry)->processStep($session, $step);

        expect($result->isSuccessful())->toBeTrue()
            ->and($session->getStepState('a'))->toBe(StepStatus::Completed);
    });
});
