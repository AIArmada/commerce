<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Targeting\Evaluators;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Targeting\Evaluators\ChannelEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\DeviceEvaluator;
use AIArmada\Vouchers\Targeting\Evaluators\FirstPurchaseEvaluator;
use AIArmada\Vouchers\Targeting\TargetingContext;
use Illuminate\Database\Eloquent\Model;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

/**
 * Simple test user for first purchase evaluation.
 */
class TestFirstPurchaseUser extends Model
{
    protected $guarded = [];
}

/**
 * Create context with specific channel, device, and user.
 */
function createContextForDeviceChannelTests(
    ?string $channel = null,
    ?string $device = null,
    bool $isFirstPurchase = false,
): TargetingContext {
    $cart = new Cart(new InMemoryStorage, 'test-' . uniqid());

    $metadata = [];
    if ($channel !== null) {
        $metadata['channel'] = $channel;
    }
    if ($device !== null) {
        $metadata['device'] = $device;
    }
    $metadata['is_first_purchase'] = $isFirstPurchase;

    $user = $isFirstPurchase ? null : new TestFirstPurchaseUser(['total_orders' => 5]);

    return new TargetingContext($cart, $user, null, $metadata);
}

describe('ChannelEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new ChannelEvaluator;
    });

    describe('supports', function (): void {
        it('returns true for channel type', function (): void {
            expect($this->evaluator->supports('channel'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('device'))->toBeFalse();
            expect($this->evaluator->supports('cart_value'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns channel', function (): void {
            expect($this->evaluator->getType())->toBe('channel');
        });
    });

    describe('evaluate', function (): void {
        it('evaluates equals operator correctly', function (): void {
            $context = createContextForDeviceChannelTests('web');

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'web'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'mobile'], $context))->toBeFalse();
        });

        it('evaluates equals operator case insensitively', function (): void {
            $context = createContextForDeviceChannelTests('WEB');

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'web'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'Web'], $context))->toBeTrue();
        });

        it('evaluates not equals operator correctly', function (): void {
            $context = createContextForDeviceChannelTests('web');

            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 'mobile'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 'web'], $context))->toBeFalse();
        });

        it('evaluates in operator correctly', function (): void {
            $context = createContextForDeviceChannelTests('web');

            expect($this->evaluator->evaluate(['operator' => 'in', 'values' => ['web', 'mobile']], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'in', 'values' => ['api', 'mobile']], $context))->toBeFalse();
        });

        it('evaluates not_in operator correctly', function (): void {
            $context = createContextForDeviceChannelTests('web');

            expect($this->evaluator->evaluate(['operator' => 'not_in', 'values' => ['api', 'mobile']], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'not_in', 'values' => ['web', 'mobile']], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            $context = createContextForDeviceChannelTests('web');

            expect($this->evaluator->evaluate(['operator' => 'unknown', 'value' => 'web'], $context))->toBeFalse();
        });

        it('uses default equals operator when not specified', function (): void {
            $context = createContextForDeviceChannelTests('web');

            expect($this->evaluator->evaluate(['value' => 'web'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['value' => 'mobile'], $context))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates in operator requires array', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'in']);

            expect($errors)->toContain('Values must be an array for in/not_in operators');
        });

        it('validates not_in operator requires array', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'not_in', 'values' => 'not-array']);

            expect($errors)->toContain('Values must be an array for in/not_in operators');
        });

        it('validates other operators require value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '=']);

            expect($errors)->toContain('Value is required');
        });

        it('validates with valid inputs', function (): void {
            $errors = $this->evaluator->validate(['operator' => '=', 'value' => 'web']);

            expect($errors)->toBe([]);
        });

        it('validates in operator with valid array', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'in', 'values' => ['web', 'mobile']]);

            expect($errors)->toBe([]);
        });
    });
});

describe('DeviceEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new DeviceEvaluator;
    });

    describe('supports', function (): void {
        it('returns true for device type', function (): void {
            expect($this->evaluator->supports('device'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('channel'))->toBeFalse();
            expect($this->evaluator->supports('cart_value'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns device', function (): void {
            expect($this->evaluator->getType())->toBe('device');
        });
    });

    describe('evaluate', function (): void {
        it('evaluates equals operator correctly', function (): void {
            $context = createContextForDeviceChannelTests(null, 'desktop');

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'desktop'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'mobile'], $context))->toBeFalse();
        });

        it('evaluates equals operator case insensitively', function (): void {
            $context = createContextForDeviceChannelTests(null, 'DESKTOP');

            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'desktop'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '=', 'value' => 'Desktop'], $context))->toBeTrue();
        });

        it('evaluates not equals operator correctly', function (): void {
            $context = createContextForDeviceChannelTests(null, 'desktop');

            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 'mobile'], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => '!=', 'value' => 'desktop'], $context))->toBeFalse();
        });

        it('evaluates in operator correctly', function (): void {
            $context = createContextForDeviceChannelTests(null, 'mobile');

            expect($this->evaluator->evaluate(['operator' => 'in', 'values' => ['mobile', 'tablet']], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'in', 'values' => ['desktop', 'tablet']], $context))->toBeFalse();
        });

        it('evaluates not_in operator correctly', function (): void {
            $context = createContextForDeviceChannelTests(null, 'desktop');

            expect($this->evaluator->evaluate(['operator' => 'not_in', 'values' => ['mobile', 'tablet']], $context))->toBeTrue();
            expect($this->evaluator->evaluate(['operator' => 'not_in', 'values' => ['desktop', 'tablet']], $context))->toBeFalse();
        });

        it('returns false for unknown operator', function (): void {
            $context = createContextForDeviceChannelTests(null, 'desktop');

            expect($this->evaluator->evaluate(['operator' => 'unknown', 'value' => 'desktop'], $context))->toBeFalse();
        });
    });

    describe('validate', function (): void {
        it('validates in operator requires array', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'in']);

            expect($errors)->toContain('Values must be an array for in/not_in operators');
        });

        it('validates invalid device type for in operator', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'in', 'values' => ['invalid']]);

            expect($errors[0])->toContain('Invalid device type');
        });

        it('validates other operators require value', function (): void {
            $errors = $this->evaluator->validate(['operator' => '=']);

            expect($errors)->toContain('Value is required');
        });

        it('validates invalid device type for equals operator', function (): void {
            $errors = $this->evaluator->validate(['operator' => '=', 'value' => 'invalid']);

            expect($errors[0])->toContain('Invalid device type');
        });

        it('validates with valid device', function (): void {
            $errors = $this->evaluator->validate(['operator' => '=', 'value' => 'desktop']);

            expect($errors)->toBe([]);
        });

        it('validates in operator with valid devices', function (): void {
            $errors = $this->evaluator->validate(['operator' => 'in', 'values' => ['mobile', 'tablet']]);

            expect($errors)->toBe([]);
        });
    });
});

describe('FirstPurchaseEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new FirstPurchaseEvaluator;
    });

    describe('supports', function (): void {
        it('returns true for first_purchase type', function (): void {
            expect($this->evaluator->supports('first_purchase'))->toBeTrue();
        });

        it('returns false for other types', function (): void {
            expect($this->evaluator->supports('device'))->toBeFalse();
            expect($this->evaluator->supports('channel'))->toBeFalse();
        });
    });

    describe('getType', function (): void {
        it('returns first_purchase', function (): void {
            expect($this->evaluator->getType())->toBe('first_purchase');
        });
    });

    describe('evaluate', function (): void {
        it('returns true for first purchase when targeting first purchases', function (): void {
            $context = createContextForDeviceChannelTests(null, null, true);

            expect($this->evaluator->evaluate(['value' => true], $context))->toBeTrue();
        });

        it('returns false for first purchase when targeting returning customers', function (): void {
            $context = createContextForDeviceChannelTests(null, null, true);

            expect($this->evaluator->evaluate(['value' => false], $context))->toBeFalse();
        });

        it('returns true for returning customer when targeting returning customers', function (): void {
            $context = createContextForDeviceChannelTests(null, null, false);

            expect($this->evaluator->evaluate(['value' => false], $context))->toBeTrue();
        });

        it('returns false for returning customer when targeting first purchases', function (): void {
            $context = createContextForDeviceChannelTests(null, null, false);

            expect($this->evaluator->evaluate(['value' => true], $context))->toBeFalse();
        });

        it('defaults to true for first purchase targeting', function (): void {
            $context = createContextForDeviceChannelTests(null, null, true);

            expect($this->evaluator->evaluate([], $context))->toBeTrue();
        });
    });

    describe('validate', function (): void {
        it('returns no errors for valid boolean value', function (): void {
            $errors = $this->evaluator->validate(['value' => true]);

            expect($errors)->toBe([]);
        });

        it('returns no errors for valid numeric boolean', function (): void {
            expect($this->evaluator->validate(['value' => 1]))->toBe([]);
            expect($this->evaluator->validate(['value' => 0]))->toBe([]);
            expect($this->evaluator->validate(['value' => '1']))->toBe([]);
            expect($this->evaluator->validate(['value' => '0']))->toBe([]);
        });

        it('returns no errors for string boolean', function (): void {
            expect($this->evaluator->validate(['value' => 'true']))->toBe([]);
            expect($this->evaluator->validate(['value' => 'false']))->toBe([]);
        });

        it('returns error for invalid value', function (): void {
            $errors = $this->evaluator->validate(['value' => 'invalid']);

            expect($errors)->toContain('Value must be a boolean');
        });

        it('returns no errors when value not set', function (): void {
            $errors = $this->evaluator->validate([]);

            expect($errors)->toBe([]);
        });
    });
});
