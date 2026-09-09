<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Target;

describe('built-in target compositions', function (): void {
    it('creates cart subtotal target', function (): void {
        $target = Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->applyAggregate()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::CART_SUBTOTAL)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart grand total target', function (): void {
        $target = Target::cart()->phase(ConditionPhase::GRAND_TOTAL)->applyAggregate()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::GRAND_TOTAL)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart shipping target', function (): void {
        $target = Target::cart()->phase(ConditionPhase::SHIPPING)->applyAggregate()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::SHIPPING)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart taxable target', function (): void {
        $target = Target::cart()->phase(ConditionPhase::TAXABLE)->applyAggregate()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::TAXABLE)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates cart tax target', function (): void {
        $target = Target::cart()->phase(ConditionPhase::TAX)->applyAggregate()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CART)
            ->and($target->phase)->toBe(ConditionPhase::TAX)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates items per item target', function (): void {
        $target = Target::items()->phase(ConditionPhase::ITEM_DISCOUNT)->applyPerItem()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::ITEMS)
            ->and($target->phase)->toBe(ConditionPhase::ITEM_DISCOUNT)
            ->and($target->application)->toBe(ConditionApplication::PER_ITEM);
    });

    it('creates items pre item target', function (): void {
        $target = Target::items()->phase(ConditionPhase::PRE_ITEM)->applyAggregate()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::ITEMS)
            ->and($target->phase)->toBe(ConditionPhase::PRE_ITEM)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });

    it('creates custom aggregate target', function (): void {
        $target = Target::custom()->build();

        expect($target)->toBeInstanceOf(ConditionTarget::class)
            ->and($target->scope)->toBe(ConditionScope::CUSTOM)
            ->and($target->phase)->toBe(ConditionPhase::CUSTOM)
            ->and($target->application)->toBe(ConditionApplication::AGGREGATE);
    });
});
