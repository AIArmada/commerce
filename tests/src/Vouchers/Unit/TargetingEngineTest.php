<?php

declare(strict_types=1);

namespace Tests\Unit\Vouchers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Enums\TargetingMode;
use AIArmada\Vouchers\Targeting\Enums\TargetingRuleType;
use AIArmada\Vouchers\Targeting\TargetingConfiguration;
use AIArmada\Vouchers\Targeting\TargetingContext;
use AIArmada\Vouchers\Targeting\TargetingEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Mockery;

function createTargetingTestCart(array $items = []): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-targeting', events: null);

    foreach ($items as $item) {
        $cart->add([
            'id' => $item['id'] ?? 'ITEM-1',
            'name' => $item['name'] ?? 'Test Item',
            'price' => $item['price'] ?? 1000,
            'quantity' => $item['quantity'] ?? 1,
            'attributes' => [
                'sku' => $item['sku'] ?? $item['id'] ?? 'ITEM-1',
                'category' => $item['category'] ?? 'general',
            ],
        ]);
    }

    return $cart;
}

function createTargetingContext(
    ?Cart $cart = null,
    ?Model $user = null,
    ?Request $request = null,
    array $metadata = []
): TargetingContext {
    $cart ??= createTargetingTestCart();

    return new TargetingContext($cart, $user, $request, $metadata);
}

class TestUserWithSegments extends Model
{
    protected $fillable = ['segments', 'customer_lifetime_value', 'is_first_purchase'];

    protected $casts = [
        'segments' => 'array',
        'customer_lifetime_value' => 'integer',
        'is_first_purchase' => 'boolean',
    ];
}

describe('TargetingRuleType Enum', function (): void {
    it('has all 15 rule types', function (): void {
        $cases = TargetingRuleType::cases();
        expect(count($cases))->toBe(15);
    });

    it('provides correct labels', function (): void {
        expect(TargetingRuleType::UserSegment->label())->toBe('User Segment');
        expect(TargetingRuleType::CartValue->label())->toBe('Cart Value');
        expect(TargetingRuleType::TimeWindow->label())->toBe('Time Window');
        expect(TargetingRuleType::Geographic->label())->toBe('Geographic Location');
    });

    it('returns appropriate operators for each type', function (): void {
        $segmentOps = TargetingRuleType::UserSegment->getOperators();
        expect(array_keys($segmentOps))->toContain('in', 'not_in', 'contains_any', 'contains_all');

        $valueOps = TargetingRuleType::CartValue->getOperators();
        expect(array_keys($valueOps))->toContain('=', '!=', '>', '>=', '<', '<=', 'between');

        $firstPurchaseOps = TargetingRuleType::FirstPurchase->getOperators();
        expect(array_keys($firstPurchaseOps))->toContain('=');
    });

    it('identifies array value types', function (): void {
        expect(TargetingRuleType::UserSegment->requiresArrayValues())->toBeTrue();
        expect(TargetingRuleType::ProductInCart->requiresArrayValues())->toBeTrue();
        expect(TargetingRuleType::CartValue->requiresArrayValues())->toBeFalse();
        expect(TargetingRuleType::FirstPurchase->requiresArrayValues())->toBeFalse();
    });

    it('provides grouped options for UI', function (): void {
        $grouped = TargetingRuleType::grouped();
        expect($grouped)->toHaveKeys(['User', 'Cart', 'Time', 'Context']);
    });
});

describe('TargetingMode Enum', function (): void {
    it('has all three modes', function (): void {
        expect(TargetingMode::cases())->toHaveCount(3);
        expect(TargetingMode::All->value)->toBe('all');
        expect(TargetingMode::Any->value)->toBe('any');
        expect(TargetingMode::Custom->value)->toBe('custom');
    });
});

describe('TargetingConfiguration', function (): void {
    it('creates from valid target_definition array', function (): void {
        $config = TargetingConfiguration::fromArray([
            'targeting' => [
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ],
        ]);

        expect($config)->not->toBeNull();
        expect($config->mode)->toBe(TargetingMode::All);
        expect($config->rules)->toHaveCount(1);
        expect($config->hasRules())->toBeTrue();
    });

    it('returns null for empty or null input', function (): void {
        expect(TargetingConfiguration::fromArray(null))->toBeNull();
        expect(TargetingConfiguration::fromArray([]))->toBeNull();
        expect(TargetingConfiguration::fromArray(['targeting' => []]))->toBeNull();
    });

    it('parses custom mode with expression', function (): void {
        $config = TargetingConfiguration::fromArray([
            'targeting' => [
                'mode' => 'custom',
                'rules' => [],
                'expression' => [
                    'and' => [
                        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ],
                ],
            ],
        ]);

        expect($config)->not->toBeNull();
        expect($config->mode)->toBe(TargetingMode::Custom);
        expect($config->expression)->not->toBeNull();
    });

    it('defaults to All mode when not specified', function (): void {
        $config = TargetingConfiguration::fromArray([
            'targeting' => [
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ],
        ]);

        expect($config->mode)->toBe(TargetingMode::All);
    });

    it('converts back to array', function (): void {
        $original = [
            'targeting' => [
                'mode' => 'any',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                ],
            ],
        ];

        $config = TargetingConfiguration::fromArray($original);
        $array = $config->toArray();

        expect($array['targeting']['mode'])->toBe('any');
        expect($array['targeting']['rules'])->toHaveCount(1);
    });
});

describe('TargetingContext', function (): void {
    it('creates from cart with auto-resolved attributes', function (): void {
        $cart = createTargetingTestCart([
            ['id' => 'ITEM-1', 'price' => 5000, 'quantity' => 2],
        ]);

        $context = TargetingContext::fromCart($cart);

        expect($context->cart)->toBe($cart);
        expect($context->getCartValue())->toBe(10000); // 5000 * 2
        expect($context->getCartQuantity())->toBe(2);
    });

    it('returns guest segment for null user', function (): void {
        $context = createTargetingContext(user: null);
        expect($context->getUserSegments())->toBe(['guest']);
    });

    it('returns user segments from model', function (): void {
        $user = new TestUserWithSegments(['segments' => ['vip', 'premium']]);
        $context = createTargetingContext(user: $user);
        expect($context->getUserSegments())->toBe(['vip', 'premium']);
    });

    it('detects first purchase status', function (): void {
        $newUser = new TestUserWithSegments(['is_first_purchase' => true]);
        $returningUser = new TestUserWithSegments(['is_first_purchase' => false]);

        expect(createTargetingContext(user: $newUser)->isFirstPurchase())->toBeTrue();
        expect(createTargetingContext(user: $returningUser)->isFirstPurchase())->toBeFalse();
    });

    it('gets channel from request header', function (): void {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X-Channel' => 'mobile']);
        $context = createTargetingContext(request: $request);
        expect($context->getChannel())->toBe('mobile');
    });

    it('gets device type from user agent', function (): void {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
        ]);
        $context = createTargetingContext(request: $request);
        expect($context->getDevice())->toBe('mobile');
    });

    it('gets timezone from metadata or config', function (): void {
        $context = createTargetingContext(metadata: ['timezone' => 'Asia/Tokyo']);
        expect($context->getTimezone())->toBe('Asia/Tokyo');
    });
});

describe('TargetingEngine', function (): void {
    it('returns true for empty targeting', function (): void {
        $engine = new TargetingEngine;
        $context = createTargetingContext();

        expect($engine->evaluate([], $context))->toBeTrue();
    });

    describe('All mode (AND logic)', function (): void {
        it('passes when all rules match', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 10000, 'quantity' => 1],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 1],
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeTrue();
        });

        it('fails when any rule fails', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 3000, 'quantity' => 1],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'all',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000], // Fails: 3000 < 5000
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 1], // Passes
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeFalse();
        });
    });

    describe('Any mode (OR logic)', function (): void {
        it('passes when any rule matches', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 3000, 'quantity' => 5],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'any',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 10000], // Fails
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 3], // Passes: 5 >= 3
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeTrue();
        });

        it('fails when no rules match', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 3000, 'quantity' => 1],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'any',
                'rules' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 10000], // Fails
                    ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 5], // Fails
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeFalse();
        });
    });

    describe('Custom mode (Boolean expressions)', function (): void {
        it('evaluates AND expressions', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 10000, 'quantity' => 2],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'custom',
                'expression' => [
                    'and' => [
                        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                        ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
                    ],
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeTrue();
        });

        it('evaluates OR expressions', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 2000, 'quantity' => 1],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'custom',
                'expression' => [
                    'or' => [
                        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000], // Fails
                        ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 1], // Passes
                    ],
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeTrue();
        });

        it('evaluates NOT expressions', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 3000, 'quantity' => 1],
            ]);
            $context = new TargetingContext($cart);

            $targeting = [
                'mode' => 'custom',
                'expression' => [
                    'not' => ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000], // cart is 3000, NOT (3000 >= 5000) = NOT false = true
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeTrue();
        });

        it('evaluates nested expressions', function (): void {
            $engine = new TargetingEngine;
            $cart = createTargetingTestCart([
                ['id' => 'ITEM-1', 'price' => 10000, 'quantity' => 3],
            ]);
            $context = new TargetingContext($cart);

            // (cart_value >= 5000 AND cart_quantity >= 2) OR cart_value >= 50000
            $targeting = [
                'mode' => 'custom',
                'expression' => [
                    'or' => [
                        [
                            'and' => [
                                ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                                ['type' => 'cart_quantity', 'operator' => '>=', 'value' => 2],
                            ],
                        ],
                        ['type' => 'cart_value', 'operator' => '>=', 'value' => 50000],
                    ],
                ],
            ];

            expect($engine->evaluate($targeting, $context))->toBeTrue();
        });
    });
});

afterEach(function (): void {
    Mockery::close();
});
