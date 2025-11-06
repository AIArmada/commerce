<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition as CoreCartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\FilamentCart\Models\Condition;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

beforeEach(function (): void {
    $container = new Container();
    Container::setInstance($container);

    $container->instance('config', new Repository([
        'cart' => [
            'money' => [
                'default_currency' => 'USD',
            ],
        ],
    ]));

    $container->instance(RulesFactoryInterface::class, new class implements RulesFactoryInterface
    {
        private array $supported = ['min-items', 'total-at-least'];

        public function createRules(string $key, array $metadata = []): array
        {
            if (! $this->canCreateRules($key)) {
                throw new InvalidArgumentException("Unsupported rule factory key [{$key}]");
            }

            return [
                static fn (array $payload = []): array => [
                    'key' => $key,
                    'context' => $metadata['context'] ?? [],
                    'payload' => $payload,
                ],
            ];
        }

        public function canCreateRules(string $key): bool
        {
            return in_array($key, $this->supported, true);
        }

        public function getAvailableKeys(): array
        {
            return $this->supported;
        }
    });
});

it('normalizes rule definitions from user input', function (): void {
    $input = [
        'factory_keys' => ['min-items', '', 123, 'total-at-least'],
        'context' => [
            'min' => '3',
            'amount' => '100.5',
            'ids' => 'sku-1, sku-2 ',
            'json' => '[1,2]',
            'truthy' => 'true',
            'falsy' => 'false',
            'empty' => ' ',
        ],
    ];

    $normalized = Condition::normalizeRulesDefinition($input, true);

    expect($normalized)
        ->toBe([
            'factory_keys' => ['min-items', 'total-at-least'],
            'context' => [
                'min' => 3,
                'amount' => 100.5,
                'ids' => ['sku-1', 'sku-2'],
                'json' => [1, 2],
                'truthy' => true,
                'falsy' => false,
            ],
        ]);
});

it('builds dynamic cart conditions using built-in rule factories', function (): void {
    $condition = new Condition();

    $condition->forceFill([
        'name' => 'bulk_discount',
        'display_name' => 'Bulk Discount',
        'type' => 'discount',
        'target' => 'subtotal',
        'value' => '-10%',
        'order' => 1,
        'rules' => [
            'factory_keys' => ['min-items', 'total-at-least'],
            'context' => [
                'min' => 2,
                'amount' => 100,
            ],
        ],
        'is_active' => true,
        'is_dynamic' => true,
    ]);

    $condition->computeDerivedFields();

    /** @var CoreCartCondition $cartCondition */
    $cartCondition = $condition->createCondition();

    $rules = $cartCondition->getRules();

    expect($cartCondition->isDynamic())->toBeTrue();
    expect($rules)->toBeArray()->toHaveCount(2);

    $results = array_map(static fn (callable $rule): array => $rule(['subtotal' => 150]), $rules);

    expect($results)->each->toMatchArray([
        'context' => [
            'min' => 2,
            'amount' => 100,
        ],
        'payload' => ['subtotal' => 150],
    ]);
    expect(array_column($results, 'key'))->toMatchArray(['min-items', 'total-at-least']);
});
