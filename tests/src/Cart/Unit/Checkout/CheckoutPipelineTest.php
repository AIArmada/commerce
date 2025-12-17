<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\CheckoutPipeline;
use AIArmada\Cart\Checkout\CheckoutResult;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\Exceptions\CheckoutException;
use AIArmada\Cart\Checkout\StageResult;
use AIArmada\Cart\Testing\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
});

describe('CheckoutPipeline', function (): void {
    it('can be instantiated with a cart', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $pipeline = new CheckoutPipeline($cart);

        expect($pipeline)->toBeInstanceOf(CheckoutPipeline::class);
    });

    it('can add a single stage', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $stage = Mockery::mock(CheckoutStageInterface::class);

        $pipeline = new CheckoutPipeline($cart);
        $result = $pipeline->addStage($stage);

        expect($result)->toBe($pipeline);
    });

    it('can add multiple stages', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $stage1 = Mockery::mock(CheckoutStageInterface::class);
        $stage2 = Mockery::mock(CheckoutStageInterface::class);

        $pipeline = new CheckoutPipeline($cart);
        $result = $pipeline->addStages([$stage1, $stage2]);

        expect($result)->toBe($pipeline);
    });

    it('can set context', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $pipeline = new CheckoutPipeline($cart);

        $result = $pipeline->withContext(['key' => 'value']);

        expect($result)->toBe($pipeline)
            ->and($pipeline->getContext())->toBe(['key' => 'value']);
    });

    it('merges context values', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $pipeline = new CheckoutPipeline($cart);

        $pipeline->withContext(['key1' => 'value1']);
        $pipeline->withContext(['key2' => 'value2']);

        expect($pipeline->getContext())->toBe(['key1' => 'value1', 'key2' => 'value2']);
    });

    it('executes all stages successfully', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage1 = Mockery::mock(CheckoutStageInterface::class);
        $stage1->shouldReceive('getName')->andReturn('stage1');
        $stage1->shouldReceive('shouldExecute')->andReturn(true);
        $stage1->shouldReceive('execute')->andReturn(StageResult::success('Stage 1 passed', ['data1' => 'value1']));

        $stage2 = Mockery::mock(CheckoutStageInterface::class);
        $stage2->shouldReceive('getName')->andReturn('stage2');
        $stage2->shouldReceive('shouldExecute')->andReturn(true);
        $stage2->shouldReceive('execute')->andReturn(StageResult::success('Stage 2 passed', ['data2' => 'value2']));

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStages([$stage1, $stage2]);

        $result = $pipeline->execute();

        expect($result)->toBeInstanceOf(CheckoutResult::class)
            ->and($result->success)->toBeTrue()
            ->and($result->completedStages)->toBe(['stage1', 'stage2'])
            ->and($result->context)->toHaveKey('data1')
            ->and($result->context)->toHaveKey('data2');
    });

    it('skips stages that should not execute', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage1 = Mockery::mock(CheckoutStageInterface::class);
        $stage1->shouldReceive('getName')->andReturn('stage1');
        $stage1->shouldReceive('shouldExecute')->andReturn(true);
        $stage1->shouldReceive('execute')->andReturn(StageResult::success('Stage 1 passed'));

        $stage2 = Mockery::mock(CheckoutStageInterface::class);
        $stage2->shouldReceive('getName')->andReturn('stage2');
        $stage2->shouldReceive('shouldExecute')->andReturn(false);
        $stage2->shouldReceive('execute')->never();

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStages([$stage1, $stage2]);

        $result = $pipeline->execute();

        expect($result->success)->toBeTrue()
            ->and($result->completedStages)->toBe(['stage1']);
    });

    it('throws CheckoutException when stage fails', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = Mockery::mock(CheckoutStageInterface::class);
        $stage->shouldReceive('getName')->andReturn('failing_stage');
        $stage->shouldReceive('shouldExecute')->andReturn(true);
        $stage->shouldReceive('execute')->andReturn(StageResult::failure('Stage failed', ['error' => 'details']));
        $stage->shouldReceive('supportsRollback')->andReturn(false);

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStage($stage);

        expect(fn () => $pipeline->execute())->toThrow(CheckoutException::class);
    });

    it('rolls back completed stages on failure', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage1 = Mockery::mock(CheckoutStageInterface::class);
        $stage1->shouldReceive('getName')->andReturn('stage1');
        $stage1->shouldReceive('shouldExecute')->andReturn(true);
        $stage1->shouldReceive('execute')->andReturn(StageResult::success('Stage 1 passed'));
        $stage1->shouldReceive('supportsRollback')->andReturn(true);
        $stage1->shouldReceive('rollback')->once();

        $stage2 = Mockery::mock(CheckoutStageInterface::class);
        $stage2->shouldReceive('getName')->andReturn('stage2');
        $stage2->shouldReceive('shouldExecute')->andReturn(true);
        $stage2->shouldReceive('execute')->andReturn(StageResult::failure('Stage 2 failed'));
        $stage2->shouldReceive('supportsRollback')->andReturn(false);

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStages([$stage1, $stage2]);

        try {
            $pipeline->execute();
        } catch (CheckoutException) {
            // Expected
        }
    });

    it('handles exception during stage execution', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = Mockery::mock(CheckoutStageInterface::class);
        $stage->shouldReceive('getName')->andReturn('error_stage');
        $stage->shouldReceive('shouldExecute')->andReturn(true);
        $stage->shouldReceive('execute')->andThrow(new Exception('Unexpected error'));
        $stage->shouldReceive('supportsRollback')->andReturn(false);

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStage($stage);

        expect(fn () => $pipeline->execute())->toThrow(CheckoutException::class);
    });

    it('returns empty completed stages before execution', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $pipeline = new CheckoutPipeline($cart);

        expect($pipeline->getCompletedStages())->toBeEmpty();
    });

    it('handles rollback exceptions gracefully', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage1 = Mockery::mock(CheckoutStageInterface::class);
        $stage1->shouldReceive('getName')->andReturn('stage1');
        $stage1->shouldReceive('shouldExecute')->andReturn(true);
        $stage1->shouldReceive('execute')->andReturn(StageResult::success('Stage 1 passed'));
        $stage1->shouldReceive('supportsRollback')->andReturn(true);
        $stage1->shouldReceive('rollback')->andThrow(new Exception('Rollback failed'));

        $stage2 = Mockery::mock(CheckoutStageInterface::class);
        $stage2->shouldReceive('getName')->andReturn('stage2');
        $stage2->shouldReceive('shouldExecute')->andReturn(true);
        $stage2->shouldReceive('execute')->andReturn(StageResult::failure('Stage 2 failed'));
        $stage2->shouldReceive('supportsRollback')->andReturn(false);

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStages([$stage1, $stage2]);

        expect(fn () => $pipeline->execute())->toThrow(CheckoutException::class);
    });

    it('skips rollback for stages that do not support it', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage1 = Mockery::mock(CheckoutStageInterface::class);
        $stage1->shouldReceive('getName')->andReturn('no_rollback_stage');
        $stage1->shouldReceive('shouldExecute')->andReturn(true);
        $stage1->shouldReceive('execute')->andReturn(StageResult::success('Passed'));
        $stage1->shouldReceive('supportsRollback')->andReturn(false);
        $stage1->shouldReceive('rollback')->never();

        $stage2 = Mockery::mock(CheckoutStageInterface::class);
        $stage2->shouldReceive('getName')->andReturn('failing');
        $stage2->shouldReceive('shouldExecute')->andReturn(true);
        $stage2->shouldReceive('execute')->andReturn(StageResult::failure('Failed'));
        $stage2->shouldReceive('supportsRollback')->andReturn(false);

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStages([$stage1, $stage2]);

        try {
            $pipeline->execute();
        } catch (CheckoutException) {
            // Expected
        }
    });

    it('returns cart in result', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = Mockery::mock(CheckoutStageInterface::class);
        $stage->shouldReceive('getName')->andReturn('stage');
        $stage->shouldReceive('shouldExecute')->andReturn(true);
        $stage->shouldReceive('execute')->andReturn(StageResult::success('Done'));

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStage($stage);

        $result = $pipeline->execute();

        expect($result->cart)->toBe($cart);
    });

    it('preserves original checkout exception', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = Mockery::mock(CheckoutStageInterface::class);
        $stage->shouldReceive('getName')->andReturn('stage');
        $stage->shouldReceive('shouldExecute')->andReturn(true);
        $stage->shouldReceive('execute')->andThrow(CheckoutException::stageFailed('stage', 'Custom error'));
        $stage->shouldReceive('supportsRollback')->andReturn(false);

        $pipeline = new CheckoutPipeline($cart);
        $pipeline->addStage($stage);

        try {
            $pipeline->execute();
            $this->fail('Expected CheckoutException');
        } catch (CheckoutException $e) {
            expect($e->getMessage())->toContain('Custom error');
        }
    });

    it('can execute empty pipeline', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $pipeline = new CheckoutPipeline($cart);

        $result = $pipeline->execute();

        expect($result->success)->toBeTrue()
            ->and($result->completedStages)->toBeEmpty();
    });
});
