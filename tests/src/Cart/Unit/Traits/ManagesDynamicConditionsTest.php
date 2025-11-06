<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Support\Facades\DB;

final class RecordingRulesFactory implements RulesFactoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $created = [];

    public function createRules(string $key, array $metadata = []): array
    {
        $this->created[] = ['key' => $key, 'metadata' => $metadata];

        return match ($key) {
            'always-true' => [
                static fn (Cart $cart, ?CartItem $item = null): bool => true,
            ],
            'min-items' => [
                static function (Cart $cart, ?CartItem $item = null) use ($metadata): bool {
                    $minimum = (int) ($metadata['context']['min_items'] ?? 0);

                    return $cart->count() >= $minimum;
                },
            ],
            'throws' => [
                static function (): bool {
                    throw new RuntimeException('dynamic rule failure');
                },
            ],
            default => throw new InvalidArgumentException("Unsupported rules factory key: {$key}"),
        };
    }

    public function canCreateRules(string $key): bool
    {
        return in_array($key, ['always-true', 'min-items', 'throws'], true);
    }

    public function getAvailableKeys(): array
    {
        return ['always-true', 'min-items', 'throws'];
    }
}

beforeEach(function (): void {
    $connection = DB::connection('testing');
    $this->storage = new DatabaseStorage($connection, 'carts');
    $this->identifier = 'dynamic-user-'.uniqid();
});

describe('dynamic condition lifecycle', function (): void {
    it('persists metadata context across registrations and restores dynamic conditions', function (): void {
        $factory = new RecordingRulesFactory();

        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'vip_discount',
                'type' => 'discount',
                'target' => 'subtotal',
                'value' => '-10%',
                'attributes' => ['label' => 'VIP'],
            ],
            rules: 'min-items',
            ruleFactoryKey: null,
            metadata: ['min_items' => 2]
        );

        $metadata = $cart->getDynamicConditionMetadata();
        expect($metadata)
            ->toHaveKey('vip_discount')
            ->and($metadata['vip_discount']['context'] ?? [])
            ->toMatchArray(['min_items' => 2]);

        expect($factory->created)->not->toBeEmpty();

        // Cart starts empty so the dynamic discount is inactive.
        expect($cart->getConditions()->has('vip_discount'))->toBeFalse();

        $cart->add('sku-1', 'Sample A', 100, 1);
        $cart->add('sku-2', 'Sample B', 80, 1);

        expect($cart->getConditions()->has('vip_discount'))->toBeTrue();

        // Spin up a new cart instance to ensure rules are restored with metadata.
        $restoredFactory = new RecordingRulesFactory();
        $restoredCart = new Cart($this->storage, $this->identifier, events: null);
        $restoredCart->withRulesFactory($restoredFactory);

        expect($restoredFactory->created)
            ->not->toBeEmpty()
            ->and($restoredFactory->created[0]['metadata']['context']['min_items'] ?? null)
            ->toBe(2);

        // Restore cart is empty, so add items to trigger evaluation again.
        $restoredCart->add('sku-1', 'Sample A', 100, 1);
        $restoredCart->add('sku-2', 'Sample B', 80, 1);

        expect($restoredCart->getConditions()->has('vip_discount'))->toBeTrue();
    });

    it('invokes the failure handler when rule execution throws an exception', function (): void {
        $factory = new RecordingRulesFactory();
        $cart = new Cart($this->storage, $this->identifier, events: null);
        $cart->withRulesFactory($factory);

        $captured = null;
        $cart->onDynamicConditionFailure(function (string $operation, ?CartCondition $condition, ?Throwable $exception, array $context) use (&$captured): void {
            $captured = compact('operation', 'condition', 'exception', 'context');
        });

        $cart->registerDynamicCondition(
            condition: [
                'name' => 'faulty_condition',
                'type' => 'discount',
                'target' => 'subtotal',
                'value' => '-5%',
            ],
            rules: 'throws'
        );

        expect($captured)->not->toBeNull();
        expect($captured['operation'])->toBe('evaluate');
        expect($captured['condition'])->toBeInstanceOf(CartCondition::class);
        expect($captured['exception'])->toBeInstanceOf(RuntimeException::class);
        expect($cart->getConditions()->has('faulty_condition'))->toBeFalse();
    });
});

describe('CartCondition helpers', function (): void {
    it('caches static clones for dynamic conditions', function (): void {
        $condition = new CartCondition(
            name: 'vip_discount',
            type: 'discount',
            target: 'subtotal',
            value: '-10%',
            rules: [static fn (): bool => true]
        );

        $firstStatic = $condition->withoutRules();
        $secondStatic = $condition->withoutRules();

        expect($firstStatic)
            ->toBeInstanceOf(CartCondition::class)
            ->and($firstStatic)->not->toBe($condition)
            ->and($secondStatic)->toBe($firstStatic)
            ->and($firstStatic->isDynamic())->toBeFalse();
    });
});
