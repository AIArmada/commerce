<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\CommerceSupport\Targeting\TargetingEngine;

it('allows empty targeting as an explicit no restrictions configuration', function (): void {
    $engine = new TargetingEngine;

    expect($engine->evaluate([], new TargetingContext(null)))->toBeTrue();
});

it('fails closed for unknown targeting rule types', function (): void {
    $engine = new TargetingEngine;

    $targeting = [
        'mode' => 'all',
        'rules' => [
            ['type' => 'does_not_exist', 'value' => true],
        ],
    ];

    expect($engine->validate($targeting))->not->toBeEmpty()
        ->and($engine->evaluate($targeting, new TargetingContext(null)))->toBeFalse();
});

it('fails closed for malformed targeting rules', function (): void {
    $engine = new TargetingEngine;

    $targeting = [
        'mode' => 'all',
        'rules' => [
            ['operator' => '>=', 'value' => 5000],
        ],
    ];

    expect($engine->validate($targeting))->toContain('Rule 0: Rule type is required')
        ->and($engine->evaluate($targeting, new TargetingContext(null)))->toBeFalse();
});

it('fails closed for invalid custom targeting expressions', function (): void {
    $engine = new TargetingEngine;

    $targeting = [
        'mode' => 'custom',
        'expression' => [],
    ];

    expect($engine->validate($targeting))->not->toBeEmpty()
        ->and($engine->evaluate($targeting, new TargetingContext(null)))->toBeFalse()
        ->and($engine->evaluateExpression([], new TargetingContext(null)))->toBeFalse();
});

it('evaluates valid targeting rules', function (): void {
    $engine = new TargetingEngine;
    $cart = new class
    {
        public function getSubtotal(): int
        {
            return 5000;
        }

        public function getTotalQuantity(): int
        {
            return 3;
        }
    };

    $targeting = [
        'mode' => 'all',
        'rules' => [
            ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
            ['type' => 'cart_quantity', 'operator' => '=', 'value' => 3],
        ],
    ];

    expect($engine->validate($targeting))->toBeEmpty()
        ->and($engine->evaluate($targeting, new TargetingContext($cart)))->toBeTrue();
});

it('validates evaluator-backed targeting rule types not listed in the legacy enum', function (): void {
    $engine = new TargetingEngine;

    $targeting = [
        'mode' => 'all',
        'rules' => [
            ['type' => 'payment_method', 'methods' => ['card']],
            ['type' => 'coupon_usage_limit', 'code' => 'WELCOME', 'max_uses' => 1],
            ['type' => 'referral_source', 'utm_source' => 'newsletter'],
        ],
    ];

    expect($engine->validate($targeting))->toBeEmpty();
});