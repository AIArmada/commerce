<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutStepRegistry;

describe('CheckoutStepRegistry', function (): void {
    it('can register a step', function (): void {
        $registry = new CheckoutStepRegistry;
        $step = createMockStep('test_step', 'Test Step');

        $registry->register('test_step', $step);

        expect($registry->has('test_step'))->toBeTrue()
            ->and($registry->get('test_step'))->toBe($step);
    });

    it('can check if step exists', function (): void {
        $registry = new CheckoutStepRegistry;
        $step = createMockStep('test_step', 'Test Step');

        $registry->register('test_step', $step);

        expect($registry->has('test_step'))->toBeTrue()
            ->and($registry->has('nonexistent'))->toBeFalse();
    });

    it('returns null for nonexistent step', function (): void {
        $registry = new CheckoutStepRegistry;

        expect($registry->get('nonexistent'))->toBeNull();
    });

    it('can get all registered steps', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);

        $all = $registry->all();

        expect($all)->toHaveCount(2)
            ->and($all)->toHaveKey('step1')
            ->and($all)->toHaveKey('step2');
    });

    it('can enable and disable steps', function (): void {
        $registry = new CheckoutStepRegistry;
        $step = createMockStep('test_step', 'Test Step');
        $registry->register('test_step', $step);

        expect($registry->isEnabled('test_step'))->toBeTrue();

        $registry->disable('test_step');
        expect($registry->isEnabled('test_step'))->toBeFalse();

        $registry->enable('test_step');
        expect($registry->isEnabled('test_step'))->toBeTrue();
    });

    it('returns ordered steps', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');
        $step3 = createMockStep('step3', 'Step 3');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);
        $registry->register('step3', $step3);

        $ordered = $registry->getOrderedSteps();

        expect($ordered)->toHaveCount(3)
            ->and($ordered[0]->getIdentifier())->toBe('step1')
            ->and($ordered[1]->getIdentifier())->toBe('step2')
            ->and($ordered[2]->getIdentifier())->toBe('step3');
    });

    it('excludes disabled steps from ordered list', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');
        $step3 = createMockStep('step3', 'Step 3');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);
        $registry->register('step3', $step3);

        $registry->disable('step2');

        $ordered = $registry->getOrderedSteps();

        expect($ordered)->toHaveCount(2)
            ->and($ordered[0]->getIdentifier())->toBe('step1')
            ->and($ordered[1]->getIdentifier())->toBe('step3');
    });

    it('can set custom order', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');
        $step3 = createMockStep('step3', 'Step 3');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);
        $registry->register('step3', $step3);

        $registry->setOrder(['step3', 'step1', 'step2']);

        $ordered = $registry->getOrderedSteps();

        expect($ordered[0]->getIdentifier())->toBe('step3')
            ->and($ordered[1]->getIdentifier())->toBe('step1')
            ->and($ordered[2]->getIdentifier())->toBe('step2');
    });

    it('can replace a step', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step1', 'Replacement Step');

        $registry->register('step1', $step1);
        $registry->replace('step1', $step2);

        expect($registry->get('step1')->getName())->toBe('Replacement Step');
    });

    it('throws exception when replacing nonexistent step', function (): void {
        $registry = new CheckoutStepRegistry;
        $step = createMockStep('step1', 'Step 1');

        expect(fn () => $registry->replace('nonexistent', $step))
            ->toThrow(CheckoutStepException::class);
    });

    it('can insert step before another', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');
        $newStep = createMockStep('new_step', 'New Step');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);
        $registry->insertBefore('step2', 'new_step', $newStep);

        $ordered = $registry->getOrderedSteps();

        expect($ordered)->toHaveCount(3)
            ->and($ordered[0]->getIdentifier())->toBe('step1')
            ->and($ordered[1]->getIdentifier())->toBe('new_step')
            ->and($ordered[2]->getIdentifier())->toBe('step2');
    });

    it('can insert step after another', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');
        $newStep = createMockStep('new_step', 'New Step');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);
        $registry->insertAfter('step1', 'new_step', $newStep);

        $ordered = $registry->getOrderedSteps();

        expect($ordered)->toHaveCount(3)
            ->and($ordered[0]->getIdentifier())->toBe('step1')
            ->and($ordered[1]->getIdentifier())->toBe('new_step')
            ->and($ordered[2]->getIdentifier())->toBe('step2');
    });

    it('throws exception when inserting before nonexistent step', function (): void {
        $registry = new CheckoutStepRegistry;
        $step = createMockStep('step1', 'Step 1');

        expect(fn () => $registry->insertBefore('nonexistent', 'step1', $step))
            ->toThrow(CheckoutStepException::class);
    });

    it('can get enabled step identifiers', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');
        $step3 = createMockStep('step3', 'Step 3');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);
        $registry->register('step3', $step3);

        $registry->disable('step2');

        $identifiers = $registry->getEnabledStepIdentifiers();

        expect($identifiers)->toContain('step1')
            ->and($identifiers)->not->toContain('step2')
            ->and($identifiers)->toContain('step3');
    });

    it('can get order', function (): void {
        $registry = new CheckoutStepRegistry;
        $step1 = createMockStep('step1', 'Step 1');
        $step2 = createMockStep('step2', 'Step 2');

        $registry->register('step1', $step1);
        $registry->register('step2', $step2);

        $order = $registry->getOrder();

        expect($order)->toBe(['step1', 'step2']);
    });
});

/**
 * Helper function to create a mock step.
 */
function createMockStep(string $identifier, string $name): CheckoutStepInterface
{
    return new class($identifier, $name) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly string $name,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function validate(CheckoutSession $session): array
        {
            return [];
        }

        public function handle(CheckoutSession $session): StepResult
        {
            return StepResult::success($this->identifier);
        }

        public function canSkip(CheckoutSession $session): bool
        {
            return false;
        }

        public function rollback(CheckoutSession $session): void {}

        public function getDependencies(): array
        {
            return [];
        }
    };
}
