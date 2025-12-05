<?php

declare(strict_types=1);

namespace Tests\Unit\Vouchers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Evaluators\CartQuantityEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CartValueEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CategoryInCartEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\ChannelEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\CustomerLifetimeValueEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DateRangeEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DayOfWeekEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DeviceEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\FirstPurchaseEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\GeographicEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\ProductInCartEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\TimeWindowEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\UserSegmentEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

function createEvaluatorTestCart(array $items = []): Cart
{
    $storage = new InMemoryStorage();
    $cart = new Cart($storage, 'test-evaluators', events: null);

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

class EvaluatorTestUser extends Model
{
    protected $fillable = ['segments', 'customer_lifetime_value', 'is_first_purchase'];

    protected $casts = [
        'segments' => 'array',
        'customer_lifetime_value' => 'integer',
        'is_first_purchase' => 'boolean',
    ];
}

describe('CartValueEvaluator', function (): void {
    it('supports cart_value type', function (): void {
        $evaluator = new CartValueEvaluator();
        expect($evaluator->supports('cart_value'))->toBeTrue();
        expect($evaluator->supports('user_segment'))->toBeFalse();
    });

    it('evaluates greater than or equal', function (): void {
        $evaluator = new CartValueEvaluator();
        $cart = createEvaluatorTestCart([
            ['id' => 'ITEM-1', 'price' => 5000, 'quantity' => 2], // Total: 10000
        ]);
        $context = new TargetingContext($cart);

        expect($evaluator->evaluate(['operator' => '>=', 'value' => 5000], $context))->toBeTrue();
        expect($evaluator->evaluate(['operator' => '>=', 'value' => 10000], $context))->toBeTrue();
        expect($evaluator->evaluate(['operator' => '>=', 'value' => 15000], $context))->toBeFalse();
    });

    it('evaluates between operator', function (): void {
        $evaluator = new CartValueEvaluator();
        $cart = createEvaluatorTestCart([
            ['id' => 'ITEM-1', 'price' => 5000, 'quantity' => 1],
        ]);
        $context = new TargetingContext($cart);

        expect($evaluator->evaluate([
            'operator' => 'between',
            'min' => 3000,
            'max' => 7000,
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'between',
            'min' => 6000,
            'max' => 10000,
        ], $context))->toBeFalse();
    });
});

describe('CartQuantityEvaluator', function (): void {
    it('evaluates total quantity in cart', function (): void {
        $evaluator = new CartQuantityEvaluator();
        $cart = createEvaluatorTestCart([
            ['id' => 'ITEM-1', 'price' => 1000, 'quantity' => 3],
            ['id' => 'ITEM-2', 'price' => 2000, 'quantity' => 2],
        ]);
        $context = new TargetingContext($cart);

        expect($evaluator->evaluate(['operator' => '>=', 'value' => 5], $context))->toBeTrue();
        expect($evaluator->evaluate(['operator' => '=', 'value' => 5], $context))->toBeTrue();
        expect($evaluator->evaluate(['operator' => '>', 'value' => 5], $context))->toBeFalse();
    });
});

describe('UserSegmentEvaluator', function (): void {
    it('checks if user is in segment', function (): void {
        $evaluator = new UserSegmentEvaluator();
        $user = new EvaluatorTestUser(['segments' => ['vip', 'premium']]);
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, $user);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['vip'],
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['basic'],
        ], $context))->toBeFalse();
    });

    it('checks if user is not in segment', function (): void {
        $evaluator = new UserSegmentEvaluator();
        $user = new EvaluatorTestUser(['segments' => ['standard']]);
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, $user);

        expect($evaluator->evaluate([
            'operator' => 'not_in',
            'values' => ['banned', 'suspended'],
        ], $context))->toBeTrue();
    });

    it('checks contains_any for partial match', function (): void {
        $evaluator = new UserSegmentEvaluator();
        $user = new EvaluatorTestUser(['segments' => ['vip', 'beta_tester']]);
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, $user);

        expect($evaluator->evaluate([
            'operator' => 'contains_any',
            'values' => ['premium', 'vip', 'gold'],
        ], $context))->toBeTrue();
    });

    it('checks contains_all for complete match', function (): void {
        $evaluator = new UserSegmentEvaluator();
        $user = new EvaluatorTestUser(['segments' => ['vip', 'premium', 'beta']]);
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, $user);

        expect($evaluator->evaluate([
            'operator' => 'contains_all',
            'values' => ['vip', 'premium'],
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'contains_all',
            'values' => ['vip', 'gold'],
        ], $context))->toBeFalse();
    });
});

describe('ProductInCartEvaluator', function (): void {
    it('checks if product is in cart', function (): void {
        $evaluator = new ProductInCartEvaluator();
        $cart = createEvaluatorTestCart([
            ['id' => 'PROD-A', 'sku' => 'SKU-A'],
            ['id' => 'PROD-B', 'sku' => 'SKU-B'],
        ]);
        $context = new TargetingContext($cart);

        // getProductIdentifiers uses SKU from attributes if available
        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['SKU-A'],
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['SKU-Z'],
        ], $context))->toBeFalse();
    });

    it('checks if product is not in cart', function (): void {
        $evaluator = new ProductInCartEvaluator();
        $cart = createEvaluatorTestCart([
            ['id' => 'PROD-A', 'sku' => 'SKU-A'],
        ]);
        $context = new TargetingContext($cart);

        expect($evaluator->evaluate([
            'operator' => 'not_in',
            'values' => ['SKU-X', 'SKU-Y'],
        ], $context))->toBeTrue();
    });
});

describe('FirstPurchaseEvaluator', function (): void {
    it('detects first purchase customer', function (): void {
        $evaluator = new FirstPurchaseEvaluator();
        $user = new EvaluatorTestUser(['is_first_purchase' => true]);
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, $user);

        expect($evaluator->evaluate(['operator' => '=', 'value' => true], $context))->toBeTrue();
        expect($evaluator->evaluate(['operator' => '=', 'value' => false], $context))->toBeFalse();
    });

    it('treats guest as first purchase', function (): void {
        $evaluator = new FirstPurchaseEvaluator();
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, null);

        expect($evaluator->evaluate(['operator' => '=', 'value' => true], $context))->toBeTrue();
    });
});

describe('CustomerLifetimeValueEvaluator', function (): void {
    it('compares customer lifetime value', function (): void {
        $evaluator = new CustomerLifetimeValueEvaluator();
        $user = new EvaluatorTestUser(['customer_lifetime_value' => 50000]);
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, $user);

        expect($evaluator->evaluate(['operator' => '>=', 'value' => 25000], $context))->toBeTrue();
        expect($evaluator->evaluate(['operator' => '>=', 'value' => 75000], $context))->toBeFalse();
    });
});

describe('TimeWindowEvaluator', function (): void {
    it('checks if current time is within window', function (): void {
        $evaluator = new TimeWindowEvaluator();
        $cart = createEvaluatorTestCart();

        Carbon::setTestNow(Carbon::parse('2024-01-15 14:30:00'));
        $context = new TargetingContext($cart, null, null, ['timezone' => 'UTC']);

        expect($evaluator->evaluate([
            'operator' => 'between',
            'start' => '09:00',
            'end' => '18:00',
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'between',
            'start' => '20:00',
            'end' => '23:59',
        ], $context))->toBeFalse();

        Carbon::setTestNow();
    });

    it('handles overnight time windows', function (): void {
        $evaluator = new TimeWindowEvaluator();
        $cart = createEvaluatorTestCart();

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:30:00'));
        $context = new TargetingContext($cart, null, null, ['timezone' => 'UTC']);

        expect($evaluator->evaluate([
            'operator' => 'between',
            'start' => '22:00',
            'end' => '02:00',
        ], $context))->toBeTrue();

        Carbon::setTestNow();
    });
});

describe('DayOfWeekEvaluator', function (): void {
    it('checks day of week by number', function (): void {
        $evaluator = new DayOfWeekEvaluator();
        $cart = createEvaluatorTestCart();

        Carbon::setTestNow(Carbon::parse('2024-01-15')); // Monday = 1
        $context = new TargetingContext($cart, null, null, ['timezone' => 'UTC']);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => [1, 2, 3], // Mon, Tue, Wed
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => [5, 6, 0], // Fri, Sat, Sun
        ], $context))->toBeFalse();

        Carbon::setTestNow();
    });

    it('checks day of week by name', function (): void {
        $evaluator = new DayOfWeekEvaluator();
        $cart = createEvaluatorTestCart();

        Carbon::setTestNow(Carbon::parse('2024-01-15')); // Monday
        $context = new TargetingContext($cart, null, null, ['timezone' => 'UTC']);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['Monday', 'Tuesday'],
        ], $context))->toBeTrue();

        Carbon::setTestNow();
    });
});

describe('DateRangeEvaluator', function (): void {
    it('checks if date is within range', function (): void {
        $evaluator = new DateRangeEvaluator();
        $cart = createEvaluatorTestCart();

        Carbon::setTestNow(Carbon::parse('2024-06-15'));
        $context = new TargetingContext($cart, null, null, ['timezone' => 'UTC']);

        expect($evaluator->evaluate([
            'operator' => 'between',
            'start' => '2024-01-01',
            'end' => '2024-12-31',
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'between',
            'start' => '2025-01-01',
            'end' => '2025-12-31',
        ], $context))->toBeFalse();

        Carbon::setTestNow();
    });
});

describe('ChannelEvaluator', function (): void {
    it('checks request channel', function (): void {
        $evaluator = new ChannelEvaluator();
        $cart = createEvaluatorTestCart();
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X-Channel' => 'mobile']);
        $context = new TargetingContext($cart, null, $request);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['mobile', 'app'],
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['web', 'desktop'],
        ], $context))->toBeFalse();
    });
});

describe('DeviceEvaluator', function (): void {
    it('detects mobile device', function (): void {
        $evaluator = new DeviceEvaluator();
        $cart = createEvaluatorTestCart();
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
        ]);
        $context = new TargetingContext($cart, null, $request);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['mobile'],
        ], $context))->toBeTrue();
    });

    it('detects desktop device', function (): void {
        $evaluator = new DeviceEvaluator();
        $cart = createEvaluatorTestCart();
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $context = new TargetingContext($cart, null, $request);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['desktop'],
        ], $context))->toBeTrue();
    });
});

describe('GeographicEvaluator', function (): void {
    it('checks country from metadata', function (): void {
        $evaluator = new GeographicEvaluator();
        $cart = createEvaluatorTestCart();
        $context = new TargetingContext($cart, null, null, ['country' => 'MY']);

        expect($evaluator->evaluate([
            'operator' => 'in',
            'values' => ['MY', 'SG', 'ID'],
        ], $context))->toBeTrue();

        expect($evaluator->evaluate([
            'operator' => 'not_in',
            'values' => ['US', 'UK'],
        ], $context))->toBeTrue();
    });
});
