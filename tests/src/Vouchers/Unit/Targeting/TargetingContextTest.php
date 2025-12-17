<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Mockery;

/**
 * Create a test cart.
 */
function createCartForContextTest(string $id = 'context-test'): Cart
{
    return new Cart(new InMemoryStorage, $id);
}

/**
 * Test user class with segment/role methods for testing those code paths.
 */
class TestUserWithMethods extends Model
{
    protected $guarded = [];

    protected array $customMethods = [];

    public function setMethodReturn(string $method, mixed $value): void
    {
        $this->customMethods[$method] = $value;
    }

    public function getSegments(): array
    {
        return $this->customMethods['getSegments'] ?? [];
    }

    public function getLifetimeValue(): int
    {
        return $this->customMethods['getLifetimeValue'] ?? 0;
    }
}

/**
 * Test user class with Spatie Permission roles (for testing getRoleNames fallback).
 */
class TestUserWithRoles extends Model
{
    protected $guarded = [];

    protected array $roles = [];

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getRoleNames(): \Illuminate\Support\Collection
    {
        return collect($this->roles);
    }
}

/**
 * Simple test user class WITHOUT special methods (only uses attributes).
 * Use this when testing attribute-based lookups that should NOT be overridden by methods.
 */
class TestUserWithAttributes extends Model
{
    protected $guarded = [];
}

/**
 * Create a test user with special methods (segments, roles, CLV method).
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $methodReturns
 */
function createTestUserWithMethods(array $attributes = [], array $methodReturns = []): TestUserWithMethods
{
    $user = new TestUserWithMethods($attributes);

    foreach ($methodReturns as $method => $value) {
        $user->setMethodReturn($method, $value);
    }

    return $user;
}

/**
 * Create a test user with Spatie Permission roles.
 *
 * @param  array<string>  $roles
 */
function createTestUserWithRoles(array $roles): TestUserWithRoles
{
    $user = new TestUserWithRoles;
    $user->setRoles($roles);

    return $user;
}

/**
 * Create a simple test user that only uses attributes (no special methods).
 *
 * @param  array<string, mixed>  $attributes
 */
function createTestUserWithAttributes(array $attributes = []): TestUserWithAttributes
{
    return new TestUserWithAttributes($attributes);
}

/**
 * Create a mock request with specified headers and user agent.
 *
 * @param  array<string, string>  $headers
 */
function createMockRequest(array $headers = [], ?string $userAgent = null): Request
{
    $mock = Mockery::mock(Request::class);

    $mock->shouldReceive('header')
        ->andReturnUsing(fn (string $key) => $headers[mb_strtolower($key)] ?? $headers[$key] ?? null);

    $mock->shouldReceive('userAgent')
        ->andReturn($userAgent);

    return $mock;
}

afterEach(function (): void {
    Mockery::close();
});

describe('TargetingContext Construction', function (): void {
    it('creates context with cart only', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->cart)->toBe($cart)
            ->and($context->user)->toBeNull()
            ->and($context->request)->toBeNull()
            ->and($context->metadata)->toBe([]);
    });

    it('creates context with all parameters', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['id' => 'user-1']);
        $request = createMockRequest();
        $metadata = ['key' => 'value'];

        $context = new TargetingContext($cart, $user, $request, $metadata);

        expect($context->cart)->toBe($cart)
            ->and($context->user)->toBe($user)
            ->and($context->request)->toBe($request)
            ->and($context->metadata)->toBe($metadata);
    });
});

describe('TargetingContext getUserSegments', function (): void {
    it('returns guest for null user', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getUserSegments())->toBe(['guest']);
    });

    it('returns segments from getSegments method', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithMethods([], ['getSegments' => ['premium', 'loyal']]);

        $context = new TargetingContext($cart, $user);

        expect($context->getUserSegments())->toBe(['premium', 'loyal']);
    });

    it('returns role names from Spatie Permission', function (): void {
        $cart = createCartForContextTest();
        // Use user with roles but NOT getSegments method
        $user = createTestUserWithRoles(['admin', 'editor']);

        $context = new TargetingContext($cart, $user);

        expect($context->getUserSegments())->toBe(['admin', 'editor']);
    });

    it('returns empty array when user has no segment methods', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['id' => 'user-1']);

        $context = new TargetingContext($cart, $user);

        expect($context->getUserSegments())->toBe([]);
    });
});

describe('TargetingContext getUserAttribute', function (): void {
    it('returns null for null user', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getUserAttribute('email'))->toBeNull();
    });

    it('returns attribute value via getAttribute', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['email' => 'test@example.com']);

        $context = new TargetingContext($cart, $user);

        expect($context->getUserAttribute('email'))->toBe('test@example.com');
    });

    it('returns null for non-existent attribute', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes([]);

        $context = new TargetingContext($cart, $user);

        expect($context->getUserAttribute('nonexistent'))->toBeNull();
    });
});

describe('TargetingContext isFirstPurchase', function (): void {
    it('returns true for guest users', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->isFirstPurchase())->toBeTrue();
    });

    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes([]);

        $context = new TargetingContext($cart, $user, null, ['is_first_purchase' => false]);

        expect($context->isFirstPurchase())->toBeFalse();
    });

    it('returns value from user attribute', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['is_first_purchase' => true]);

        $context = new TargetingContext($cart, $user);

        expect($context->isFirstPurchase())->toBeTrue();
    });

    it('returns true when total_orders is zero', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['total_orders' => 0]);

        $context = new TargetingContext($cart, $user);

        expect($context->isFirstPurchase())->toBeTrue();
    });

    it('returns false when total_orders is non-zero', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['total_orders' => 5]);

        $context = new TargetingContext($cart, $user);

        expect($context->isFirstPurchase())->toBeFalse();
    });

    it('returns false when no determinable info', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes([]);

        $context = new TargetingContext($cart, $user);

        expect($context->isFirstPurchase())->toBeFalse();
    });
});

describe('TargetingContext getCustomerLifetimeValue', function (): void {
    it('returns zero for null user', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getCustomerLifetimeValue())->toBe(0);
    });

    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes([]);

        $context = new TargetingContext($cart, $user, null, ['clv' => 50000]);

        expect($context->getCustomerLifetimeValue())->toBe(50000);
    });

    it('returns value from getLifetimeValue method', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithMethods([], ['getLifetimeValue' => 75000]);

        $context = new TargetingContext($cart, $user);

        expect($context->getCustomerLifetimeValue())->toBe(75000);
    });

    it('returns value from customer_lifetime_value attribute', function (): void {
        $cart = createCartForContextTest();
        // Use attribute-only user so method_exists check fails and we fall through to attributes
        $user = createTestUserWithAttributes(['customer_lifetime_value' => 100000]);

        $context = new TargetingContext($cart, $user);

        expect($context->getCustomerLifetimeValue())->toBe(100000);
    });

    it('returns value from lifetime_value attribute', function (): void {
        $cart = createCartForContextTest();
        // Use attribute-only user so method_exists check fails and we fall through to attributes
        $user = createTestUserWithAttributes(['lifetime_value' => 60000]);

        $context = new TargetingContext($cart, $user);

        expect($context->getCustomerLifetimeValue())->toBe(60000);
    });

    it('returns value from total_spent attribute', function (): void {
        $cart = createCartForContextTest();
        // Use attribute-only user so method_exists check fails and we fall through to attributes
        $user = createTestUserWithAttributes(['total_spent' => 30000]);

        $context = new TargetingContext($cart, $user);

        expect($context->getCustomerLifetimeValue())->toBe(30000);
    });

    it('returns zero when no CLV info available', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes([]);

        $context = new TargetingContext($cart, $user);

        expect($context->getCustomerLifetimeValue())->toBe(0);
    });
});

describe('TargetingContext getChannel', function (): void {
    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart, null, null, ['channel' => 'mobile']);

        expect($context->getChannel())->toBe('mobile');
    });

    it('returns value from X-Channel header', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest(['x-channel' => 'api']);

        $context = new TargetingContext($cart, null, $request);

        expect($context->getChannel())->toBe('api');
    });

    it('returns value from X-Sales-Channel header', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest(['x-sales-channel' => 'pos']);

        $context = new TargetingContext($cart, null, $request);

        expect($context->getChannel())->toBe('pos');
    });

    it('returns web as default', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getChannel())->toBe('web');
    });
});

describe('TargetingContext getDevice', function (): void {
    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart, null, null, ['device' => 'mobile']);

        expect($context->getDevice())->toBe('mobile');
    });

    it('returns desktop when no request', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getDevice())->toBe('desktop');
    });

    it('detects tablet from user agent', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest([], 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)');

        $context = new TargetingContext($cart, null, $request);

        expect($context->getDevice())->toBe('tablet');
    });

    it('detects mobile from user agent', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest([], 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)');

        $context = new TargetingContext($cart, null, $request);

        expect($context->getDevice())->toBe('mobile');
    });

    it('detects android mobile from user agent', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest([], 'Mozilla/5.0 (Linux; Android 11; Mobile)');

        $context = new TargetingContext($cart, null, $request);

        expect($context->getDevice())->toBe('mobile');
    });

    it('returns desktop for normal browser', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest([], 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

        $context = new TargetingContext($cart, null, $request);

        expect($context->getDevice())->toBe('desktop');
    });
});

describe('TargetingContext getCountry', function (): void {
    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart, null, null, ['country' => 'MY']);

        expect($context->getCountry())->toBe('MY');
    });

    it('returns value from Cloudflare header', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest(['cf-ipcountry' => 'US']);

        $context = new TargetingContext($cart, null, $request);

        expect($context->getCountry())->toBe('US');
    });

    it('returns value from X-Country header', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest(['x-country' => 'GB']);

        $context = new TargetingContext($cart, null, $request);

        expect($context->getCountry())->toBe('GB');
    });

    it('returns value from user country attribute', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['country' => 'AU']);

        $context = new TargetingContext($cart, $user);

        expect($context->getCountry())->toBe('AU');
    });

    it('returns value from user country_code attribute', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['country_code' => 'SG']);

        $context = new TargetingContext($cart, $user);

        expect($context->getCountry())->toBe('SG');
    });

    it('returns null when no country info', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getCountry())->toBeNull();
    });
});

describe('TargetingContext getReferrer', function (): void {
    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart, null, null, ['referrer' => 'https://google.com']);

        expect($context->getReferrer())->toBe('https://google.com');
    });

    it('returns value from Referer header', function (): void {
        $cart = createCartForContextTest();
        $request = createMockRequest(['referer' => 'https://facebook.com']);

        $context = new TargetingContext($cart, null, $request);

        expect($context->getReferrer())->toBe('https://facebook.com');
    });

    it('returns null when no referrer info', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getReferrer())->toBeNull();
    });
});

describe('TargetingContext getTimezone', function (): void {
    it('returns value from metadata', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart, null, null, ['timezone' => 'Asia/Kuala_Lumpur']);

        expect($context->getTimezone())->toBe('Asia/Kuala_Lumpur');
    });

    it('returns value from user attribute', function (): void {
        $cart = createCartForContextTest();
        $user = createTestUserWithAttributes(['timezone' => 'America/New_York']);

        $context = new TargetingContext($cart, $user);

        expect($context->getTimezone())->toBe('America/New_York');
    });

    it('returns UTC as fallback', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        // Default from config or UTC
        expect($context->getTimezone())->toBeString();
    });
});

describe('TargetingContext getCurrentTime', function (): void {
    it('returns Carbon instance', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getCurrentTime())->toBeInstanceOf(Carbon::class);
    });

    it('uses specified timezone', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        $time = $context->getCurrentTime('America/New_York');

        expect($time->timezone->getName())->toBe('America/New_York');
    });
});

describe('TargetingContext getMetadata', function (): void {
    it('returns metadata value', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart, null, null, ['custom_key' => 'custom_value']);

        expect($context->getMetadata('custom_key'))->toBe('custom_value');
    });

    it('returns default for missing key', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getMetadata('missing', 'default'))->toBe('default');
    });

    it('returns null for missing key without default', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getMetadata('missing'))->toBeNull();
    });
});

describe('TargetingContext getCartValue', function (): void {
    it('returns cart raw subtotal', function (): void {
        $cart = createCartForContextTest();
        // Empty cart returns 0
        $context = new TargetingContext($cart);

        expect($context->getCartValue())->toBe(0);
    });
});

describe('TargetingContext getCartQuantity', function (): void {
    it('returns zero for empty cart', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getCartQuantity())->toBe(0);
    });
});

describe('TargetingContext getProductIdentifiers', function (): void {
    it('returns empty array for empty cart', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getProductIdentifiers())->toBe([]);
    });
});

describe('TargetingContext getProductCategories', function (): void {
    it('returns empty array for empty cart', function (): void {
        $cart = createCartForContextTest();
        $context = new TargetingContext($cart);

        expect($context->getProductCategories())->toBe([]);
    });
});
