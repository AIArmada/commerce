<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

function createCartForFeatureTest(int $subtotalCents = 10000, array $items = []): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-feature-cart');

    if (! empty($items)) {
        foreach ($items as $item) {
            $cart->add($item);
        }

        return $cart;
    }

    $pricePerItem = max(100, (int) ($subtotalCents / 2));
    $quantityNeeded = max(1, (int) ceil($subtotalCents / $pricePerItem));

    for ($i = 1; $i <= $quantityNeeded; $i++) {
        $itemPrice = ($i === $quantityNeeded)
            ? $subtotalCents - (($quantityNeeded - 1) * $pricePerItem)
            : $pricePerItem;

        $cart->add([
            'id' => "item-{$i}",
            'name' => "Product {$i}",
            'price' => $itemPrice,
            'quantity' => 1,
        ]);
    }

    return $cart;
}

function createUserModelForFeatureTest(array $attributes = []): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };

    foreach ($attributes as $key => $value) {
        $model->setAttribute($key, $value);
    }

    return $model;
}

describe('CartFeatureExtractor', function (): void {
    beforeEach(function (): void {
        $this->extractor = new CartFeatureExtractor;
    });

    describe('extract', function (): void {
        it('extracts combined features from all contexts', function (): void {
            $cart = createCartForFeatureTest(10000);

            $features = $this->extractor->extract($cart);

            // Should have cart features
            expect($features)->toHaveKeys([
                'cart_value_cents',
                'item_count',
                'unique_items',
            ]);

            // Should have user features (guest defaults)
            expect($features)->toHaveKeys([
                'is_authenticated',
                'user_order_count',
            ]);

            // Should have session features (null request defaults)
            expect($features)->toHaveKeys([
                'session_duration_seconds',
                'device_type',
            ]);

            // Should have time features
            expect($features)->toHaveKeys([
                'hour_of_day',
                'day_of_week',
                'is_weekend',
            ]);
        });

        it('extracts with user context', function (): void {
            $cart = createCartForFeatureTest(10000);
            $user = createUserModelForFeatureTest([
                'orders_count' => 5,
                'lifetime_value' => 50000,
            ]);

            $features = $this->extractor->extract($cart, $user);

            expect($features['is_authenticated'])->toBeTrue()
                ->and($features['user_order_count'])->toBe(5)
                ->and($features['user_lifetime_value_cents'])->toBe(50000);
        });
    });

    describe('extractCartFeatures', function (): void {
        it('extracts basic cart value features', function (): void {
            $cart = createCartForFeatureTest(10000);

            $features = $this->extractor->extractCartFeatures($cart);

            expect($features['cart_value_cents'])->toBe(10000)
                ->and($features['cart_value_bucket'])->toBe('large'); // 10000 cents = $100 → large bucket
        });

        it('extracts item count features', function (): void {
            $cart = createCartForFeatureTest(10000, [
                ['id' => 'item-1', 'name' => 'Product 1', 'price' => 5000, 'quantity' => 2],
                ['id' => 'item-2', 'name' => 'Product 2', 'price' => 3000, 'quantity' => 1],
            ]);

            $features = $this->extractor->extractCartFeatures($cart);

            // countItems() returns unique items count, not total quantity
            expect($features['item_count'])->toBe(2) // 2 unique items
                ->and($features['unique_items'])->toBe(2);
        });

        it('calculates average item price', function (): void {
            $cart = createCartForFeatureTest(10000, [
                ['id' => 'item-1', 'name' => 'Product 1', 'price' => 3000, 'quantity' => 2],
                ['id' => 'item-2', 'name' => 'Product 2', 'price' => 4000, 'quantity' => 1],
            ]);

            $features = $this->extractor->extractCartFeatures($cart);

            // avg_item_price_cents = subtotal / itemCount (unique items)
            // Total: 3000*2 + 4000*1 = 10000, unique items: 2
            // Average: 10000/2 = 5000
            expect($features['avg_item_price_cents'])->toBe(5000);
        });

        it('extracts max and min item prices', function (): void {
            $cart = createCartForFeatureTest(10000, [
                ['id' => 'item-1', 'name' => 'Cheap', 'price' => 1000, 'quantity' => 1],
                ['id' => 'item-2', 'name' => 'Medium', 'price' => 3000, 'quantity' => 1],
                ['id' => 'item-3', 'name' => 'Expensive', 'price' => 6000, 'quantity' => 1],
            ]);

            $features = $this->extractor->extractCartFeatures($cart);

            expect($features['max_item_price_cents'])->toBe(6000)
                ->and($features['min_item_price_cents'])->toBe(1000);
        });

        it('handles empty cart', function (): void {
            $storage = new InMemoryStorage;
            $cart = new Cart($storage, 'empty-cart');

            $features = $this->extractor->extractCartFeatures($cart);

            expect($features['cart_value_cents'])->toBe(0)
                ->and($features['item_count'])->toBe(0)
                ->and($features['avg_item_price_cents'])->toBe(0)
                ->and($features['max_item_price_cents'])->toBe(0)
                ->and($features['min_item_price_cents'])->toBe(0);
        });

        it('detects single item cart', function (): void {
            $cart = createCartForFeatureTest(5000, [
                ['id' => 'item-1', 'name' => 'Product', 'price' => 5000, 'quantity' => 1],
            ]);

            $features = $this->extractor->extractCartFeatures($cart);

            expect($features['has_single_item'])->toBeTrue()
                ->and($features['has_bulk_purchase'])->toBeFalse();
        });

        it('detects bulk purchase', function (): void {
            // has_bulk_purchase = itemCount >= 5 (unique items, not quantity)
            $cart = createCartForFeatureTest(10000, [
                ['id' => 'item-1', 'name' => 'Product 1', 'price' => 2000, 'quantity' => 1],
                ['id' => 'item-2', 'name' => 'Product 2', 'price' => 2000, 'quantity' => 1],
                ['id' => 'item-3', 'name' => 'Product 3', 'price' => 2000, 'quantity' => 1],
                ['id' => 'item-4', 'name' => 'Product 4', 'price' => 2000, 'quantity' => 1],
                ['id' => 'item-5', 'name' => 'Product 5', 'price' => 2000, 'quantity' => 1],
            ]);

            $features = $this->extractor->extractCartFeatures($cart);

            expect($features['has_bulk_purchase'])->toBeTrue();
        });

        it('detects high value items', function (): void {
            $cart = createCartForFeatureTest(15000, [
                ['id' => 'item-1', 'name' => 'Expensive', 'price' => 15000, 'quantity' => 1],
            ]);

            $features = $this->extractor->extractCartFeatures($cart);

            // High value = >= $100 = 10000 cents
            expect($features['has_high_value_items'])->toBeTrue();
        });

        it('extracts condition features', function (): void {
            $cart = createCartForFeatureTest(10000);

            $features = $this->extractor->extractCartFeatures($cart);

            expect($features)->toHaveKeys([
                'has_conditions',
                'conditions_count',
                'total_discount_cents',
                'discount_percentage',
            ]);
        });

        it('categorizes cart value buckets correctly', function (): void {
            // Micro < $25
            $microCart = createCartForFeatureTest(2000);
            expect($this->extractor->extractCartFeatures($microCart)['cart_value_bucket'])
                ->toBe('micro');

            // Small $25-50
            $smallCart = createCartForFeatureTest(3500);
            expect($this->extractor->extractCartFeatures($smallCart)['cart_value_bucket'])
                ->toBe('small');

            // Medium $50-100
            $mediumCart = createCartForFeatureTest(7500);
            expect($this->extractor->extractCartFeatures($mediumCart)['cart_value_bucket'])
                ->toBe('medium');

            // Large $100-250
            $largeCart = createCartForFeatureTest(15000);
            expect($this->extractor->extractCartFeatures($largeCart)['cart_value_bucket'])
                ->toBe('large');

            // Premium $250-500
            $premiumCart = createCartForFeatureTest(35000);
            expect($this->extractor->extractCartFeatures($premiumCart)['cart_value_bucket'])
                ->toBe('premium');

            // Luxury $500+
            $luxuryCart = createCartForFeatureTest(60000);
            expect($this->extractor->extractCartFeatures($luxuryCart)['cart_value_bucket'])
                ->toBe('luxury');
        });

        it('calculates price variance', function (): void {
            // Single item = 0 variance
            $singleCart = createCartForFeatureTest(5000, [
                ['id' => 'item-1', 'name' => 'Product', 'price' => 5000, 'quantity' => 1],
            ]);
            expect($this->extractor->extractCartFeatures($singleCart)['price_variance'])
                ->toBe(0.0);

            // Multiple items with same price = 0 variance
            $sameCart = createCartForFeatureTest(10000, [
                ['id' => 'item-1', 'name' => 'Product 1', 'price' => 5000, 'quantity' => 1],
                ['id' => 'item-2', 'name' => 'Product 2', 'price' => 5000, 'quantity' => 1],
            ]);
            expect($this->extractor->extractCartFeatures($sameCart)['price_variance'])
                ->toBe(0.0);

            // Different prices = positive variance
            $variedCart = createCartForFeatureTest(10000, [
                ['id' => 'item-1', 'name' => 'Cheap', 'price' => 2000, 'quantity' => 1],
                ['id' => 'item-2', 'name' => 'Expensive', 'price' => 8000, 'quantity' => 1],
            ]);
            expect($this->extractor->extractCartFeatures($variedCart)['price_variance'])
                ->toBeGreaterThan(0.0);
        });
    });

    describe('extractUserFeatures', function (): void {
        it('returns guest defaults for null user', function (): void {
            $features = $this->extractor->extractUserFeatures(null);

            expect($features['is_authenticated'])->toBeFalse()
                ->and($features['user_order_count'])->toBe(0)
                ->and($features['user_lifetime_value_cents'])->toBe(0)
                ->and($features['user_segment'])->toBe('guest')
                ->and($features['is_new_customer'])->toBeTrue()
                ->and($features['voucher_usage_rate'])->toBe(0.0);
        });

        it('extracts authenticated user features', function (): void {
            $user = createUserModelForFeatureTest([
                'orders_count' => 10,
                'lifetime_value' => 100000,
                'average_order_value' => 10000,
                'segment' => 'vip',
            ]);

            $features = $this->extractor->extractUserFeatures($user);

            expect($features['is_authenticated'])->toBeTrue()
                ->and($features['user_order_count'])->toBe(10)
                ->and($features['user_lifetime_value_cents'])->toBe(100000)
                ->and($features['user_avg_order_value_cents'])->toBe(10000)
                ->and($features['user_segment'])->toBe('vip')
                ->and($features['is_new_customer'])->toBeFalse();
        });

        it('identifies new customers correctly', function (): void {
            $newUser = createUserModelForFeatureTest([
                'orders_count' => 0,
            ]);

            $features = $this->extractor->extractUserFeatures($newUser);

            expect($features['is_new_customer'])->toBeTrue();
        });

        it('calculates voucher usage rate', function (): void {
            $user = createUserModelForFeatureTest([
                'orders_count' => 10,
                'voucher_orders_count' => 3,
            ]);

            $features = $this->extractor->extractUserFeatures($user);

            expect($features['voucher_usage_rate'])->toBe(0.3);
        });

        it('calculates refund rate', function (): void {
            $user = createUserModelForFeatureTest([
                'orders_count' => 10,
                'refund_count' => 2,
            ]);

            $features = $this->extractor->extractUserFeatures($user);

            expect($features['user_refund_rate'])->toBe(0.2);
        });

        it('handles days since last order', function (): void {
            $user = createUserModelForFeatureTest([
                'last_order_at' => Carbon::now()->subDays(5),
            ]);

            $features = $this->extractor->extractUserFeatures($user);

            expect($features['days_since_last_order'])->toBe(5);
        });

        it('handles null last order date', function (): void {
            $user = createUserModelForFeatureTest([
                'last_order_at' => null,
            ]);

            $features = $this->extractor->extractUserFeatures($user);

            expect($features['days_since_last_order'])->toBeNull();
        });

        it('handles string date for last order', function (): void {
            $user = createUserModelForFeatureTest([
                'last_order_at' => Carbon::now()->subDays(3)->toDateTimeString(),
            ]);

            $features = $this->extractor->extractUserFeatures($user);

            expect($features['days_since_last_order'])->toBe(3);
        });
    });

    describe('extractSessionFeatures', function (): void {
        it('returns defaults for null request', function (): void {
            $features = $this->extractor->extractSessionFeatures(null);

            expect($features['session_duration_seconds'])->toBe(0)
                ->and($features['pages_viewed'])->toBe(0)
                ->and($features['device_type'])->toBe('unknown')
                ->and($features['is_mobile'])->toBeFalse()
                ->and($features['is_returning_visitor'])->toBeFalse()
                ->and($features['referrer_type'])->toBe('unknown')
                ->and($features['channel'])->toBe('web');
        });

        it('detects mobile device', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Mobile');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['device_type'])->toBe('mobile')
                ->and($features['is_mobile'])->toBeTrue();
        });

        it('detects tablet device', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('User-Agent', 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['device_type'])->toBe('tablet');
        });

        it('detects desktop device', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['device_type'])->toBe('desktop');
        });

        it('detects android mobile', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 11; SM-G991U)');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['device_type'])->toBe('mobile');
        });

        it('categorizes google referrer as search', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('Referer', 'https://www.google.com/search?q=test');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['referrer_type'])->toBe('search');
        });

        it('categorizes bing referrer as search', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('Referer', 'https://www.bing.com/search?q=test');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['referrer_type'])->toBe('search');
        });

        it('categorizes social referrers', function (): void {
            $facebookRequest = Request::create('/', 'GET');
            $facebookRequest->headers->set('Referer', 'https://www.facebook.com/share');
            expect($this->extractor->extractSessionFeatures($facebookRequest)['referrer_type'])
                ->toBe('social');

            $twitterRequest = Request::create('/', 'GET');
            $twitterRequest->headers->set('Referer', 'https://twitter.com/status');
            expect($this->extractor->extractSessionFeatures($twitterRequest)['referrer_type'])
                ->toBe('social');

            $instagramRequest = Request::create('/', 'GET');
            $instagramRequest->headers->set('Referer', 'https://www.instagram.com/p/abc');
            expect($this->extractor->extractSessionFeatures($instagramRequest)['referrer_type'])
                ->toBe('social');
        });

        it('categorizes email referrers', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('Referer', 'https://outlook.live.com/mail/');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['referrer_type'])->toBe('email');
        });

        it('categorizes direct traffic', function (): void {
            $request = Request::create('/', 'GET');
            // No referer header

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['referrer_type'])->toBe('direct');
        });

        it('categorizes unknown referrer as referral', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('Referer', 'https://some-blog.com/article');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['referrer_type'])->toBe('referral');
        });

        it('reads channel from header', function (): void {
            $request = Request::create('/', 'GET');
            $request->headers->set('X-Channel', 'mobile_app');

            $features = $this->extractor->extractSessionFeatures($request);

            expect($features['channel'])->toBe('mobile_app');
        });
    });

    describe('extractTimeFeatures', function (): void {
        it('extracts current time features', function (): void {
            $features = $this->extractor->extractTimeFeatures();

            expect($features)->toHaveKeys([
                'hour_of_day',
                'day_of_week',
                'is_weekend',
                'is_business_hours',
                'is_evening',
                'month_of_year',
                'is_end_of_month',
                'day_of_month',
            ]);

            expect($features['hour_of_day'])->toBeInt()
                ->and($features['hour_of_day'])->toBeGreaterThanOrEqual(0)
                ->and($features['hour_of_day'])->toBeLessThanOrEqual(23);

            expect($features['day_of_week'])->toBeInt()
                ->and($features['day_of_week'])->toBeGreaterThanOrEqual(0)
                ->and($features['day_of_week'])->toBeLessThanOrEqual(6);

            expect($features['is_weekend'])->toBeBool();
            expect($features['is_business_hours'])->toBeBool();
            expect($features['is_evening'])->toBeBool();

            expect($features['month_of_year'])->toBeInt()
                ->and($features['month_of_year'])->toBeGreaterThanOrEqual(1)
                ->and($features['month_of_year'])->toBeLessThanOrEqual(12);

            expect($features['day_of_month'])->toBeInt()
                ->and($features['day_of_month'])->toBeGreaterThanOrEqual(1)
                ->and($features['day_of_month'])->toBeLessThanOrEqual(31);
        });

        it('detects end of month correctly', function (): void {
            Carbon::setTestNow(Carbon::create(2024, 1, 26));
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_end_of_month'])->toBeTrue();

            Carbon::setTestNow(Carbon::create(2024, 1, 15));
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_end_of_month'])->toBeFalse();

            Carbon::setTestNow(); // Reset
        });

        it('detects business hours correctly', function (): void {
            Carbon::setTestNow(Carbon::create(2024, 1, 15, 10, 0)); // 10 AM
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_business_hours'])->toBeTrue();

            Carbon::setTestNow(Carbon::create(2024, 1, 15, 20, 0)); // 8 PM
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_business_hours'])->toBeFalse();

            Carbon::setTestNow(); // Reset
        });

        it('detects evening correctly', function (): void {
            Carbon::setTestNow(Carbon::create(2024, 1, 15, 20, 0)); // 8 PM
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_evening'])->toBeTrue();

            Carbon::setTestNow(Carbon::create(2024, 1, 15, 3, 0)); // 3 AM
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_evening'])->toBeTrue();

            Carbon::setTestNow(Carbon::create(2024, 1, 15, 12, 0)); // Noon
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_evening'])->toBeFalse();

            Carbon::setTestNow(); // Reset
        });

        it('detects weekend correctly', function (): void {
            Carbon::setTestNow(Carbon::create(2024, 1, 13)); // Saturday
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_weekend'])->toBeTrue();

            Carbon::setTestNow(Carbon::create(2024, 1, 15)); // Monday
            $features = $this->extractor->extractTimeFeatures();
            expect($features['is_weekend'])->toBeFalse();

            Carbon::setTestNow(); // Reset
        });
    });
});
